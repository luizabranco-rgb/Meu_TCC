<?php
declare(strict_types=1);

const SESSION_LIFETIME = 30 * 24 * 60 * 60;

ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
session_name('mathplay_session');
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

function logSessionEvent(string $event, array $details = []): void
{
    $logPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'session.log';
    $entry = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'event' => $event,
        'action' => substr((string) ($_GET['action'] ?? 'unknown'), 0, 80),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'unknown'),
        'session_cookie_present' => isset($_COOKIE[session_name()]),
        'authenticated' => isset($_SESSION['user_id']),
        'details' => $details,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($line === false || file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log('MathRabbit: não foi possível gravar o log de sessão em ' . $logPath);
    }
}

$previousActivity = $_SESSION['last_activity_at'] ?? null;
$action = substr((string) ($_GET['action'] ?? 'unknown'), 0, 80);
if ($action === 'bootstrap') {
    logSessionEvent('bootstrap', [
        'csrf_token_present' => isset($_SESSION['csrf']),
        'idle_seconds' => is_int($previousActivity) ? max(0, time() - $previousActivity) : null,
        'cookie_lifetime_seconds' => (int) ini_get('session.cookie_lifetime'),
        'gc_maxlifetime_seconds' => (int) ini_get('session.gc_maxlifetime'),
    ]);
}
$_SESSION['last_activity_at'] = time();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): never
{
    if ($status >= 400) {
        logSessionEvent('response_error', ['status' => $status]);
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'conexao.php';
    $pdo = conectar();
    return $pdo;
}

function input(): array
{
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        respond(['error' => 'Envie os dados em JSON válido.'], 400);
    }
    return $decoded;
}

function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        respond(['error' => 'Método não permitido.'], 405);
    }
}

function requireCsrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($stored) || $stored === '' || !hash_equals($stored, $sent)) {
        logSessionEvent('csrf_rejected', [
            'sent_token_present' => is_string($sent) && $sent !== '',
            'stored_token_present' => is_string($stored) && $stored !== '',
        ]);
        respond(['error' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 419);
    }
}

function currentUser(PDO $pdo): array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        logSessionEvent('authentication_missing');
        respond(['error' => 'Entre na sua conta para continuar.'], 401);
    }
    $statement = $pdo->prepare('
        SELECT id, name, email, role, coins, total_points, accessibility_enabled, font_size
        FROM users WHERE id = ?
    ');
    $statement->execute([$userId]);
    $user = $statement->fetch();
    if (!$user) {
        logSessionEvent('authenticated_user_not_found');
        session_destroy();
        respond(['error' => 'Sua conta não está mais disponível. Entre novamente.'], 401);
    }
    return $user;
}

function games(): array
{
    return [
        ['id' => 'inteiros', 'title' => 'Batalha dos Inteiros', 'topic' => 'Números positivos e negativos', 'icon' => '🚀', 'color' => 'blue'],
        ['id' => 'fracoes', 'title' => 'Poções de Frações', 'topic' => 'Frações e operações', 'icon' => '🧪', 'color' => 'purple'],
        ['id' => 'equacoes', 'title' => 'Cofre das Equações', 'topic' => 'Equações de 1º grau', 'icon' => '🔐', 'color' => 'pink'],
        ['id' => 'geometria', 'title' => 'Construtores Cósmicos', 'topic' => 'Área e perímetro', 'icon' => '🪐', 'color' => 'cyan'],
        ['id' => 'corrida', 'title' => 'Corrida das Tabuadas', 'topic' => 'Multiplicação em alta velocidade', 'icon' => '🏎️', 'color' => 'orange'],
        ['id' => 'codigo', 'title' => 'Código Secreto', 'topic' => 'Cálculo mental no teclado', 'icon' => '🔢', 'color' => 'green'],
        ['id' => 'ponte', 'title' => 'Ponte dos Padrões', 'topic' => 'Sequências e regularidades', 'icon' => '🌉', 'color' => 'pink'],
        ['id' => 'pro', 'title' => 'Modo PRO', 'topic' => 'Matemática avançada · 6º ao 9º ano', 'icon' => '👑', 'color' => 'pro'],
    ];
}

function companionCatalog(): array
{
    $catalog = json_decode(
        file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'companions.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    if (!is_array($catalog)) {
        throw new RuntimeException('O catálogo de companions está inválido.');
    }
    return $catalog;
}

function characters(): array
{
    return [
        ['id' => 'rabbit', 'name' => 'Coelho', 'icon' => '🐰', 'threshold' => 0, 'description' => 'Sua forma inicial, disponível desde o primeiro ponto.'],
        ['id' => 'turtle', 'name' => 'Tartaruga', 'icon' => '🐢', 'threshold' => 100, 'description' => 'Desbloqueada ao alcançar 100 pontos.'],
        ['id' => 'fox', 'name' => 'Raposa', 'icon' => '🦊', 'threshold' => 300, 'description' => 'Desbloqueada ao alcançar 300 pontos.'],
        ['id' => 'panda', 'name' => 'Pandinha', 'icon' => '🐼', 'threshold' => 600, 'description' => 'Desbloqueado ao alcançar 600 pontos.'],
        ['id' => 'unicorn', 'name' => 'Unicórnio', 'icon' => '🦄', 'threshold' => 1000, 'description' => 'Desbloqueado ao alcançar 1.000 pontos.'],
    ];
}

function synchronizeCharacters(PDO $pdo, int $userId, ?int $totalPoints = null): array
{
    if ($totalPoints === null) {
        $statement = $pdo->prepare('SELECT total_points, unlocked_characters, active_character FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('Não foi possível carregar a progressão dos personagens.');
        }
        $totalPoints = (int) $user['total_points'];
    } else {
        $statement = $pdo->prepare('SELECT unlocked_characters, active_character FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('Não foi possível carregar a progressão dos personagens.');
        }
    }

    $unlockedCharacters = json_decode((string) $user['unlocked_characters'], true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($unlockedCharacters)) {
        throw new RuntimeException('A lista de personagens desbloqueados está inválida.');
    }
    $unlockedCharacters = array_values(array_unique(array_map('strval', $unlockedCharacters)));
    if (!in_array('rabbit', $unlockedCharacters, true)) {
        array_unshift($unlockedCharacters, 'rabbit');
    }

    $newlyUnlocked = [];
    foreach (characters() as $character) {
        if ($totalPoints >= $character['threshold'] && !in_array($character['id'], $unlockedCharacters, true)) {
            $unlockedCharacters[] = $character['id'];
            $newlyUnlocked[] = $character;
        }
    }

    $activeCharacter = (string) ($user['active_character'] ?? 'rabbit');
    if (!in_array($activeCharacter, $unlockedCharacters, true)) {
        $activeCharacter = 'rabbit';
    }
    if ($newlyUnlocked !== []) {
        $activeCharacter = $newlyUnlocked[array_key_last($newlyUnlocked)]['id'];
    }

    $statement = $pdo->prepare('UPDATE users SET unlocked_characters = ?, active_character = ? WHERE id = ?');
    $statement->execute([json_encode($unlockedCharacters, JSON_THROW_ON_ERROR), $activeCharacter, $userId]);
    return [
        'unlocked_characters' => $unlockedCharacters,
        'active_character' => $activeCharacter,
        'newly_unlocked' => $newlyUnlocked,
    ];
}

function userStats(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare("
        SELECT COUNT(*) AS games_played,
               COALESCE(SUM(correct_count), 0) AS correct_answers,
               COALESCE(SUM(total_questions), 0) AS questions_answered,
               COALESCE(MAX(score), 0) AS best_score
        FROM game_runs WHERE user_id = ? AND status = 'completed'
    ");
    $statement->execute([$userId]);
    $stats = $statement->fetch();
    $stats['games_played'] = (int) $stats['games_played'];
    $stats['correct_answers'] = (int) $stats['correct_answers'];
    $stats['questions_answered'] = (int) $stats['questions_answered'];
    $stats['best_score'] = (int) $stats['best_score'];
    $stats['accuracy'] = $stats['questions_answered'] > 0
        ? (int) round($stats['correct_answers'] * 100 / $stats['questions_answered'])
        : 0;
    return $stats;
}

function achievementsFor(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.icon, ua.unlocked_at
        FROM achievements a
        LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
        ORDER BY ua.unlocked_at IS NULL, ua.unlocked_at DESC, a.id
    ");
    $statement->execute([$userId]);
    return $statement->fetchAll();
}

function petState(PDO $pdo, int $userId): array
{
    $characterProgress = synchronizeCharacters($pdo, $userId);
    $statement = $pdo->prepare('SELECT pet_name, energy, last_decay_time, affection_count, owned_companions, equipped_companion_id FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $pet = $statement->fetch();
    if (!$pet) {
        throw new RuntimeException('Não foi possível carregar os dados do personagem.');
    }

    $lastDecayDate = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        (string) $pet['last_decay_time'],
        new DateTimeZone('UTC')
    );
    if ($lastDecayDate === false) {
        throw new RuntimeException('O horário de energia do personagem está inválido.');
    }
    $lastDecay = $lastDecayDate->getTimestamp();
    $now = time();
    $decayIntervals = intdiv(max(0, $now - $lastDecay), 600);
    if ($decayIntervals > 0) {
        $energy = max(0, (int) $pet['energy'] - ($decayIntervals * 10));
        $lastDecay += $decayIntervals * 600;
        $statement = $pdo->prepare('UPDATE users SET energy = ?, last_decay_time = ? WHERE id = ?');
        $statement->execute([$energy, gmdate('Y-m-d H:i:s', $lastDecay), $userId]);
        $pet['energy'] = $energy;
        $pet['last_decay_time'] = gmdate('Y-m-d H:i:s', $lastDecay);
    }

    $ownedCompanions = json_decode((string) $pet['owned_companions'], true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($ownedCompanions)) {
        throw new RuntimeException('A lista de companions está inválida.');
    }

    return [
        'name' => (string) $pet['pet_name'],
        'energy' => (int) $pet['energy'],
        'last_decay_time' => (string) $pet['last_decay_time'],
        'unlocked_characters' => $characterProgress['unlocked_characters'],
        'active_character' => $characterProgress['active_character'],
        'affection_count' => (int) $pet['affection_count'],
        'owned_companions' => array_values(array_unique(array_map('strval', $ownedCompanions))),
        'equipped_companion_id' => $pet['equipped_companion_id'] !== null ? (string) $pet['equipped_companion_id'] : null,
    ];
}

function studentLeaderboard(PDO $pdo): array
{
    $statement = $pdo->prepare("
        SELECT ranked.position, ranked.id, ranked.name, ranked.total_points,
               ranked.correct_answers, ranked.games_played
        FROM (
            SELECT ROW_NUMBER() OVER (
                       ORDER BY u.total_points DESC, u.name COLLATE utf8mb4_unicode_ci ASC, u.id ASC
                   ) AS position,
                   u.id, u.name, u.total_points,
                   COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.correct_count ELSE 0 END), 0) AS correct_answers,
                   COALESCE(SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END), 0) AS games_played
            FROM users u
            LEFT JOIN game_runs r ON r.user_id = u.id
            WHERE u.role = 'student'
            GROUP BY u.id
        ) AS ranked
        ORDER BY ranked.position
    ");
    $statement->execute();
    return $statement->fetchAll();
}

function unlockAchievement(PDO $pdo, int $userId, string $achievementId): void
{
    $statement = $pdo->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)');
    $statement->execute([$userId, $achievementId]);
}

function gcd(int $a, int $b): int
{
    $a = abs($a);
    $b = abs($b);
    while ($b !== 0) {
        [$a, $b] = [$b, $a % $b];
    }
    return max(1, $a);
}

function fractionText(int $numerator, int $denominator): string
{
    if ($numerator === 0) {
        return '0';
    }
    if ($denominator < 0) {
        $numerator *= -1;
        $denominator *= -1;
    }
    $divisor = gcd($numerator, $denominator);
    $numerator = intdiv($numerator, $divisor);
    $denominator = intdiv($denominator, $divisor);
    return $denominator === 1 ? (string) $numerator : $numerator . '/' . $denominator;
}

function shuffledChoices(string $answer, array $wrongAnswers): array
{
    $choices = array_values(array_unique(array_map('strval', array_merge([$answer], $wrongAnswers))));
    for ($candidate = 0; count($choices) < 4; $candidate++) {
        $fallback = (string) $candidate;
        if (!in_array($fallback, $choices, true)) {
            $choices[] = $fallback;
        }
    }
    shuffle($choices);
    return $choices;
}

function makeAdvancedBridgeQuestion(): array
{
    $pattern = random_int(0, 2);

    if ($pattern === 0) {
        $starts = [random_int(1, 4), random_int(10, 13), random_int(20, 23)];
        $steps = [random_int(1, 2), random_int(1, 2), random_int(1, 2)];
        $missing = random_int(3, 4);
        $answer = $starts[$missing % 3] + ($steps[$missing % 3] * intdiv($missing, 3));
        $sequence = [];
        for ($index = 0; $index < 8; $index++) {
            $value = $starts[$index % 3] + ($steps[$index % 3] * intdiv($index, 3));
            $sequence[] = $index === $missing ? '?' : (string) $value;
        }
        return [
            'prompt' => 'Modo avançado: descubra o termo que falta no padrão A–B–C!',
            'answer' => (string) $answer,
            'hint' => 'O padrão se repete de três em três posições. Compare separadamente as posições 1, 4 e 7; depois 2, 5 e 8.',
            'explanation' => 'Leia cada grupo de três posições separadamente: nas posições 1, 4 e 7, some ' . $steps[0] . '; nas posições 2, 5 e 8, some ' . $steps[1] . '; nas posições 3 e 6, some ' . $steps[2] . '. O termo que faltava é ' . $answer . '.',
            'choices' => shuffledChoices((string) $answer, [(string) ($answer + 1), (string) max(0, $answer - 1), (string) ($answer + 2)]),
            'sequence' => $sequence,
        ];
    }

    if ($pattern === 1) {
        $start = random_int(2, 8);
        $difference = random_int(1, 4);
        $differenceIncrease = random_int(1, 2);
        $missing = random_int(2, 4);
        $sequence = [(string) $start];
        for ($index = 1; $index < 6; $index++) {
            $sequence[] = (string) ((int) $sequence[$index - 1] + $difference + ($differenceIncrease * ($index - 1)));
        }
        $answer = (int) $sequence[$missing];
        $sequence[$missing] = '?';
        return [
            'prompt' => 'Modo avançado: descubra o termo que falta na progressão!',
            'answer' => (string) $answer,
            'hint' => 'Observe as diferenças entre os termos: elas também seguem uma sequência.',
            'explanation' => "As diferenças aumentam de $differenceIncrease em $differenceIncrease: some $difference, depois " . ($difference + $differenceIncrease) . ', depois ' . ($difference + (2 * $differenceIncrease)) . '. O termo que faltava é ' . $answer . '.',
            'choices' => shuffledChoices((string) $answer, [(string) ($answer + 1), (string) max(0, $answer - 2), (string) ($answer + $differenceIncrease)]),
            'sequence' => $sequence,
        ];
    }

    $start = random_int(1, 3);
    $ratio = 2;
    $missing = random_int(2, 4);
    $sequence = [];
    for ($index = 0; $index < 6; $index++) {
        $sequence[] = (string) ($start * ($ratio ** $index));
    }
    $answer = (int) $sequence[$missing];
    $sequence[$missing] = '?';
    return [
        'prompt' => 'Modo avançado: descubra o termo que falta na sequência multiplicativa!',
        'answer' => (string) $answer,
        'hint' => 'Em vez de somar, descubra por quanto cada termo é multiplicado para chegar ao próximo.',
        'explanation' => "Cada termo é multiplicado por $ratio. O termo anterior é " . ($answer / $ratio) . " e o próximo é " . ($answer * $ratio) . '; o termo que faltava é ' . $answer . '.',
        'choices' => shuffledChoices((string) $answer, [(string) ($answer + 1), (string) max(0, intdiv($answer, $ratio)), (string) ($answer * $ratio)]),
        'sequence' => $sequence,
    ];
}

function makeProQuestion(): array
{
    $type = random_int(0, 7);

    if ($type === 0) {
        $totalTickets = random_int(18, 30);
        $adultPrice = random_int(25, 40);
        $studentPrice = random_int(12, 24);
        $studentTickets = random_int(4, $totalTickets - 4);
        $totalRevenue = ($totalTickets - $studentTickets) * $adultPrice + $studentTickets * $studentPrice;
        return [
            'prompt' => "Um cinema vendeu $totalTickets ingressos. O adulto custa R$ $adultPrice e o estudante R$ $studentPrice. A bilheteria arrecadou R$ $totalRevenue. Quantos ingressos de estudante foram vendidos?",
            'answer' => (string) $studentTickets,
            'hint' => 'Monte um sistema: adultos + estudantes = total de ingressos. Separe a arrecadação como se todos pagassem o ingresso adulto e observe a diferença.',
            'explanation' => "Se todos pagassem R$ $adultPrice, seriam R$ " . ($totalTickets * $adultPrice) . '. Cada ingresso de estudante reduz R$ ' . ($adultPrice - $studentPrice) . '. A diferença é R$ ' . ($totalTickets * $adultPrice - $totalRevenue) . ', então ' . ($totalTickets * $adultPrice - $totalRevenue) . ' ÷ ' . ($adultPrice - $studentPrice) . " = $studentTickets estudantes.",
            'choices' => shuffledChoices((string) $studentTickets, [(string) ($studentTickets + 2), (string) max(0, $studentTickets - 2), (string) ($totalTickets - $studentTickets)]),
        ];
    }

    if ($type === 1) {
        $x = random_int(4, 16);
        $firstOffset = random_int(2, 5);
        $secondOffset = random_int(6, 10);
        $area = ($x + $firstOffset) * ($x + $secondOffset);
        return [
            'prompt' => "Um terreno retangular tem lados de (x + $firstOffset) m e (x + $secondOffset) m. Se a área é $area m² e x é positivo, quanto vale x?",
            'answer' => (string) $x,
            'hint' => 'Transforme a área em uma equação do 2º grau: multiplique os lados e procure uma fatoração que produza a área informada.',
            'explanation' => "(x + $firstOffset)(x + $secondOffset) = $area. Expandindo: x² + " . ($firstOffset + $secondOffset) . "x + " . ($firstOffset * $secondOffset - $area) . ' = 0. A fatoração tem raiz positiva x = ' . $x . '.',
            'choices' => shuffledChoices((string) $x, [(string) ($x + 2), (string) max(1, $x - 2), (string) ($x + $firstOffset)]),
        ];
    }

    if ($type === 2) {
        $scale = random_int(1, 4);
        $horizontal = 5 * $scale;
        $vertical = 12 * $scale;
        $cable = 13 * $scale;
        return [
            'prompt' => "Um cabo liga o topo de um poste de $vertical m a uma estaca no chão, a $horizontal m da base. Qual é o comprimento do cabo, em metros?",
            'answer' => (string) $cable,
            'hint' => 'O poste e a distância no chão formam os catetos de um triângulo retângulo. Use a² + b² = c².',
            'explanation' => "{$vertical}² + {$horizontal}² = " . ($vertical * $vertical + $horizontal * $horizontal) . ' = ' . ($cable * $cable) . "; portanto, a hipotenusa mede $cable m.",
            'choices' => shuffledChoices((string) $cable, [(string) ($cable + $scale), (string) ($cable - $scale), (string) ($vertical + $horizontal)]),
        ];
    }

    if ($type === 3) {
        $scale = random_int(1, 5);
        $opposite = 3 * $scale;
        $hypotenuse = 5 * $scale;
        return [
            'prompt' => "Uma rampa forma um triângulo retângulo. Para o ângulo com o chão, o lado oposto mede $opposite m e a rampa (hipotenusa) mede $hypotenuse m. Qual é o seno desse ângulo em porcentagem?",
            'answer' => '60',
            'hint' => 'Seno = cateto oposto ÷ hipotenusa. Transforme a fração em porcentagem.',
            'explanation' => "sen(θ) = $opposite ÷ $hypotenuse = 3/5 = 0,6 = 60%.",
            'choices' => shuffledChoices('60', ['40', '75', '80']),
        ];
    }

    if ($type === 4) {
        $width = random_int(12, 24);
        $height = random_int(10, 20);
        $cutWidth = random_int(3, 7);
        $cutHeight = random_int(2, 5);
        $tileCost = random_int(4, 9);
        $floorCost = ($width * $height - $cutWidth * $cutHeight) * $tileCost;
        return [
            'prompt' => "Um pátio retangular de $width m por $height m terá um canteiro sem piso de $cutWidth m por $cutHeight m. Se cada m² de piso custa R$ $tileCost, quanto custa cobrir apenas o restante do pátio?",
            'answer' => (string) $floorCost,
            'hint' => 'Calcule a área do retângulo maior, subtraia a área do canteiro e multiplique o resultado pelo custo por metro quadrado.',
            'explanation' => "Área do pátio: $width × $height = " . ($width * $height) . ' m². Canteiro: ' . "$cutWidth × $cutHeight = " . ($cutWidth * $cutHeight) . '. Área revestida: ' . ($width * $height - $cutWidth * $cutHeight) . " m². Custo total: " . ($width * $height - $cutWidth * $cutHeight) . " × R$ $tileCost = R$ $floorCost.",
            'choices' => shuffledChoices((string) $floorCost, [(string) ($floorCost + ($cutWidth * $cutHeight * $tileCost)), (string) ($floorCost - $tileCost), (string) ($width * $height * $tileCost)]),
        ];
    }

    if ($type === 5) {
        $originalPrice = random_int(2, 40) * 1000;
        $discount = [10, 20, 25][random_int(0, 2)];
        $increase = [10, 20, 25][random_int(0, 2)];
        $afterDiscount = $originalPrice * (100 - $discount) / 100;
        $finalPrice = (int) round($afterDiscount * (100 + $increase) / 100);
        return [
            'prompt' => "Um equipamento custa R$ $originalPrice. A loja dá $discount% de desconto e depois aplica uma taxa de entrega de $increase% sobre o preço já descontado. Qual é o preço final?",
            'answer' => (string) $finalPrice,
            'hint' => 'As porcentagens são aplicadas em etapas e sobre valores diferentes: primeiro reduza o preço e só depois calcule a taxa.',
            'explanation' => "Após o desconto: R$ $originalPrice × " . (100 - $discount) . '% = R$ ' . $afterDiscount . '. A taxa é R$ ' . ($finalPrice - $afterDiscount) . ". Total: R$ $finalPrice.",
            'choices' => shuffledChoices((string) $finalPrice, [(string) ($originalPrice + 10), (string) ($finalPrice + 20), (string) max(0, $originalPrice - 10)]),
        ];
    }

    if ($type === 6) {
        $x = random_int(3, 15);
        $sum = (3 * $x + 2) + ($x - 1);
        $difference = (3 * $x + 2) - ($x - 1);
        $value = $sum * $difference;
        return [
            'prompt' => "Sem desenvolver cada quadrado separadamente, calcule (3x + 2)² − (x − 1)² para x = $x.",
            'answer' => (string) $value,
            'hint' => 'Use a diferença de quadrados: a² − b² = (a − b)(a + b). Depois substitua x.',
            'explanation' => "(3x + 2)² − (x − 1)² = [(3x + 2) − (x − 1)][(3x + 2) + (x − 1)]. Para x = $x: $sum × $difference = $value.",
            'choices' => shuffledChoices((string) $value, [(string) ($value + 2 * $x), (string) max(0, $value - 2 * $x), (string) ($sum + $difference)]),
        ];
    }

    $base = random_int(2, 5);
    $firstExponent = random_int(5, 8);
    $secondExponent = random_int(2, 5);
    $divisorExponent = random_int(2, min($firstExponent + $secondExponent - 1, 8));
    $resultExponent = $firstExponent + $secondExponent - $divisorExponent;
    $value = $base ** $resultExponent;
    return [
        'prompt' => "Simplifique usando as propriedades das potências e calcule: ($base^{$firstExponent} × $base^{$secondExponent}) ÷ $base^{$divisorExponent}.",
        'answer' => (string) $value,
        'hint' => 'Na multiplicação de potências da mesma base, some os expoentes; na divisão, subtraia.',
        'explanation' => "$base^{$firstExponent} × $base^{$secondExponent} ÷ $base^{$divisorExponent} = $base^{$resultExponent} = $value.",
        'choices' => shuffledChoices((string) $value, [(string) ($value + $base), (string) max(1, intdiv($value, $base)), (string) ($value * $base)]),
    ];
}

function makeQuestion(string $game, string $difficulty, int $questionNumber = 0): array
{
    if ($game === 'pro') {
        return makeProQuestion();
    }

    $scale = match ($difficulty) {
        'easy' => 8,
        'medium' => 16,
        default => 28,
    };

    if ($game === 'inteiros') {
        $left = random_int(1, $scale);
        $right = random_int(1, $scale);
        $operation = random_int(0, 1) === 0 ? '+' : '-';
        $negativeLeft = random_int(0, 1) === 1;
        $negativeRight = random_int(0, 1) === 1;
        $signedLeft = $negativeLeft ? -$left : $left;
        $signedRight = $negativeRight ? -$right : $right;
        $answer = $operation === '+' ? $signedLeft + $signedRight : $signedLeft - $signedRight;
        $leftText = $negativeLeft ? "(-$left)" : (string) $left;
        $rightText = $negativeRight ? "(-$right)" : (string) $right;
        return [
            'prompt' => "Quanto é $leftText $operation $rightText?",
            'answer' => (string) $answer,
            'hint' => 'Lembre-se: subtrair um número negativo é o mesmo que somar o positivo.',
            'explanation' => "$leftText $operation $rightText = $answer. Desenhe os números na reta numérica para conferir.",
            'choices' => shuffledChoices((string) $answer, [(string) -$answer, (string) ($answer + 2), (string) ($answer - 2)]),
        ];
    }

    if ($game === 'fracoes') {
        if ($difficulty !== 'easy') {
            $minimumDenominator = $difficulty === 'hard' ? 3 : 2;
            $maximumDenominator = $difficulty === 'hard' ? 12 : 9;
            $firstDenominator = random_int($minimumDenominator, $maximumDenominator);
            do {
                $secondDenominator = random_int($minimumDenominator, $maximumDenominator);
            } while ($firstDenominator === $secondDenominator);

            $a = random_int(1, $firstDenominator - 1);
            $b = random_int(1, $secondDenominator - 1);
            $operation = $difficulty === 'medium'
                ? ($questionNumber % 2 === 0 ? '+' : '-')
                : (random_int(0, 1) === 0 ? '+' : '-');
            $leftNumerator = $a * $secondDenominator;
            $rightNumerator = $b * $firstDenominator;
            if ($operation === '-') {
                while ($leftNumerator === $rightNumerator) {
                    $b = random_int(1, $secondDenominator - 1);
                    $rightNumerator = $b * $firstDenominator;
                }
                if ($leftNumerator < $rightNumerator) {
                    [$a, $b] = [$b, $a];
                    [$firstDenominator, $secondDenominator] = [$secondDenominator, $firstDenominator];
                    [$leftNumerator, $rightNumerator] = [$rightNumerator, $leftNumerator];
                }
            }

            $commonDenominator = intdiv(
                $firstDenominator * $secondDenominator,
                gcd($firstDenominator, $secondDenominator)
            );
            $leftNumerator = $a * intdiv($commonDenominator, $firstDenominator);
            $rightNumerator = $b * intdiv($commonDenominator, $secondDenominator);
            $numerator = $operation === '+'
                ? $leftNumerator + $rightNumerator
                : $leftNumerator - $rightNumerator;
            $answer = fractionText($numerator, $commonDenominator);
            $wrong = [
                fractionText($operation === '+' ? $a + $b : $a - $b, $commonDenominator),
                fractionText($operation === '+' ? $a + $b : $a - $b, $firstDenominator + $secondDenominator),
                fractionText($leftNumerator + $rightNumerator, $commonDenominator),
            ];

            return [
                'prompt' => "Resolva e simplifique: $a/$firstDenominator $operation $b/$secondDenominator",
                'answer' => $answer,
                'hint' => 'Encontre um denominador comum (o MMC dos denominadores), transforme as frações equivalentes e só então some ou subtraia os numeradores.',
                'explanation' => "$a/$firstDenominator $operation $b/$secondDenominator = $leftNumerator/$commonDenominator "
                    . ($operation === '+' ? '+' : '−') . " $rightNumerator/$commonDenominator = $numerator/$commonDenominator; simplificando, o resultado é $answer.",
                'choices' => shuffledChoices($answer, $wrong),
            ];
        }

        $denominator = random_int(2, $difficulty === 'hard' ? 12 : 8);
        $a = random_int(1, $denominator - 1);
        $b = random_int(1, $denominator - 1);
        $operation = random_int(0, 1) === 0 || $a >= $b ? '+' : '-';
        $numerator = $operation === '+' ? $a + $b : $a - $b;
        $answer = fractionText($numerator, $denominator);
        $wrong = [
            fractionText($operation === '+' ? $a + $b : $a - $b, $denominator + $denominator),
            fractionText($a + $b, $denominator),
            fractionText($numerator + 1, $denominator),
        ];
        return [
            'prompt' => "Resolva e simplifique: $a/$denominator $operation $b/$denominator",
            'answer' => $answer,
            'hint' => 'Como os denominadores são iguais, some ou subtraia os numeradores e mantenha o denominador.',
            'explanation' => "$a/$denominator $operation $b/$denominator = $numerator/$denominator, que simplificado é $answer.",
            'choices' => shuffledChoices($answer, $wrong),
        ];
    }

    if ($game === 'equacoes') {
        $x = random_int(-$scale, $scale);
        [$minimumCoefficient, $maximumCoefficient] = match ($difficulty) {
            'easy' => [2, 4],
            'medium' => [2, 6],
            default => [3, 8],
        };
        $coefficient = random_int($minimumCoefficient, $maximumCoefficient);
        $constant = random_int(-$scale, $scale);
        $result = $coefficient * $x + $constant;
        $signedConstant = $constant >= 0 ? "+ $constant" : "- " . abs($constant);
        $inverseOperation = $constant >= 0
            ? "subtraia $constant dos dois lados"
            : 'some ' . abs($constant) . ' aos dois lados';
        return [
            'prompt' => "Descubra x: {$coefficient}x $signedConstant = $result",
            'answer' => (string) $x,
            'hint' => 'Isole o termo com x: passe o número que está somando ou subtraindo para o outro lado.',
            'explanation' => "Para isolar x, $inverseOperation: {$coefficient}x = " . ($result - $constant) . ". Depois divida por $coefficient: x = $x.",
            'choices' => shuffledChoices((string) $x, [(string) -$x, (string) ($x + 1), (string) ($x - 1)]),
        ];
    }

    if ($game === 'corrida' || $game === 'codigo') {
        if ($game === 'corrida' && $difficulty === 'hard') {
            $left = random_int(11, 25);
            $right = random_int(11, 25);
        } else {
            [$minimum, $maximum] = match ($difficulty) {
                'easy' => [2, 9],
                'medium' => [3, 11],
                default => [4, 13],
            };
            $left = random_int($minimum, $maximum);
            $right = random_int($minimum, $maximum);
        }
        $answer = $left * $right;
        $isAdvancedRace = $game === 'corrida' && $difficulty === 'hard';
        $wrongAnswers = [
            (string) ($answer + $left),
            (string) max(1, $answer - $right),
            (string) ($left + $right),
        ];
        return [
            'prompt' => $game === 'corrida'
                ? "🏁 Acelere! Quanto é $left × $right?"
                : "Desvende o código: quanto é $left × $right?",
            'answer' => (string) $answer,
            'hint' => $isAdvancedRace
                ? "Separe $right em dezenas e unidades e aplique a distributiva: multiplique $left por cada parte e depois some os resultados."
                : "Pense na tabuada do $left: some $left ao todo $right vezes.",
            'explanation' => $isAdvancedRace
                ? "$left × $right = $left × " . (intdiv($right, 10) * 10) . " + $left × " . ($right % 10) . ' = ' . ($left * intdiv($right, 10) * 10) . ' + ' . ($left * ($right % 10)) . " = $answer."
                : "$left × $right = $answer. Você pode conferir somando $left em grupos de $right.",
            'choices' => shuffledChoices((string) $answer, $wrongAnswers),
        ];
    }

    if ($game === 'ponte') {
        if ($difficulty === 'hard') {
            return makeAdvancedBridgeQuestion();
        }
        $step = $difficulty === 'easy' ? random_int(2, 5) : random_int(3, 7);
        $start = random_int(1, $difficulty === 'easy' ? 15 : 25);
        $missing = random_int(1, 3);
        $answer = $start + ($step * $missing);
        $sequence = [];
        for ($index = 0; $index < 5; $index++) {
            $sequence[] = $index === $missing ? '?' : (string) ($start + ($step * $index));
        }
        return [
            'prompt' => 'Encontre o número que completa a ponte!',
            'answer' => (string) $answer,
            'hint' => "Observe quanto cada número aumenta. A regra desta sequência é somar $step.",
            'explanation' => "A sequência aumenta de $step em $step. O número que faltava é $answer.",
            'choices' => shuffledChoices((string) $answer, [
                (string) ($answer + $step),
                (string) max(0, $answer - $step),
                (string) ($answer + 1),
            ]),
            'sequence' => $sequence,
        ];
    }

    $geometryScale = match ($difficulty) {
        'easy' => 8,
        'medium' => 12,
        default => 18,
    };
    $width = random_int(2, $geometryScale);
    $height = random_int(2, $geometryScale);
    $askArea = random_int(0, 1) === 0;
    $answer = $askArea ? $width * $height : 2 * ($width + $height);
    $type = $askArea ? 'área' : 'perímetro';
    $questionType = $askArea ? 'a área' : 'o perímetro';
    $calculation = $askArea ? "$width × $height" : "2 × ($width + $height)";
    return [
        'prompt' => "Um retângulo mede $width cm por $height cm. Qual é $questionType?",
        'answer' => (string) $answer,
        'hint' => $askArea ? 'Área do retângulo = base × altura.' : 'Perímetro é a soma de todos os lados: 2 × (base + altura).',
        'explanation' => "O $type é calculado por $calculation = $answer cm" . ($askArea ? '².' : '.'),
        'choices' => shuffledChoices((string) $answer, [(string) ($answer + $width), (string) max(1, $answer - $height), (string) ($width + $height)]),
    ];
}

function numericAnswer(string $value): ?float
{
    $value = str_replace(['−', ',', ' '], ['-', '.', ''], trim($value));
    if (preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
        return (float) $value;
    }
    if (preg_match('/^(-?\d+)\/(\d+)$/', $value, $matches) && (int) $matches[2] !== 0) {
        return (float) $matches[1] / (int) $matches[2];
    }
    return null;
}

function answerMatches(string $submitted, string $expected): bool
{
    $actualNumber = numericAnswer($submitted);
    $expectedNumber = numericAnswer($expected);
    return $actualNumber !== null && $expectedNumber !== null
        && abs($actualNumber - $expectedNumber) < 0.000001;
}

function runById(PDO $pdo, int $runId, int $userId): array
{
    $statement = $pdo->prepare('SELECT * FROM game_runs WHERE id = ? AND user_id = ?');
    $statement->execute([$runId, $userId]);
    $run = $statement->fetch();
    if (!$run || $run['status'] !== 'in_progress') {
        respond(['error' => 'Essa partida não está mais ativa. Comece uma nova fase.'], 409);
    }
    return $run;
}

try {
    $pdo = database();
    $action = $_GET['action'] ?? '';

    if ($action === 'bootstrap') {
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId)) {
            respond(['csrf' => $_SESSION['csrf'], 'user' => null]);
        }
        $user = currentUser($pdo);
        $history = $pdo->prepare("
            SELECT r.id, r.game_key, r.difficulty, r.score, r.correct_count,
                   r.total_questions, r.completed_at
            FROM game_runs r WHERE r.user_id = ? AND r.status = 'completed'
            ORDER BY r.completed_at DESC, r.id DESC LIMIT 6
        ");
        $history->execute([$userId]);
        $answerHistory = $pdo->prepare("
            SELECT a.id, a.question_text, a.expected_answer, a.submitted_answer,
                   a.is_correct, a.explanation, a.answered_at, r.game_key
            FROM answers a
            JOIN game_runs r ON r.id = a.run_id
            WHERE r.user_id = ?
            ORDER BY a.answered_at DESC, a.id DESC
            LIMIT 8
        ");
        $answerHistory->execute([$userId]);
        $completedStatement = $pdo->prepare("SELECT COUNT(*) FROM game_runs WHERE user_id = ? AND status = 'completed'");
        $completedStatement->execute([$userId]);
        $completedRuns = (int) $completedStatement->fetchColumn();
        $correctStatement = $pdo->prepare("
            SELECT COALESCE(SUM(correct_count), 0) FROM game_runs
            WHERE user_id = ? AND status = 'completed'
        ");
        $correctStatement->execute([$userId]);
        $performanceStatement = $pdo->prepare("
            WITH ranked_answers AS (
                SELECT r.game_key, a.is_correct,
                       ROW_NUMBER() OVER (
                           PARTITION BY r.game_key
                           ORDER BY a.answered_at DESC, a.id DESC
                       ) AS answer_rank
                FROM answers a
                JOIN game_runs r ON r.id = a.run_id
                WHERE r.user_id = ?
            ),
            totals AS (
                SELECT game_key, COUNT(*) AS attempted, SUM(is_correct) AS correct,
                       SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) AS incorrect
                FROM ranked_answers GROUP BY game_key
            ),
            recent AS (
                SELECT game_key, COUNT(*) AS recent_attempted, SUM(is_correct) AS recent_correct
                FROM ranked_answers WHERE answer_rank <= 10 GROUP BY game_key
            ),
            previous AS (
                SELECT game_key, COUNT(*) AS previous_attempted, SUM(is_correct) AS previous_correct
                FROM ranked_answers WHERE answer_rank BETWEEN 11 AND 20 GROUP BY game_key
            )
            SELECT totals.game_key, totals.attempted, totals.correct, totals.incorrect,
                   COALESCE(recent.recent_attempted, 0) AS recent_attempted,
                   COALESCE(recent.recent_correct, 0) AS recent_correct,
                   COALESCE(previous.previous_attempted, 0) AS previous_attempted,
                   COALESCE(previous.previous_correct, 0) AS previous_correct
            FROM totals
            LEFT JOIN recent ON recent.game_key = totals.game_key
            LEFT JOIN previous ON previous.game_key = totals.game_key
            ORDER BY totals.incorrect DESC, totals.attempted DESC, totals.game_key
        ");
        $performanceStatement->execute([$userId]);
        respond([
            'csrf' => $_SESSION['csrf'],
            'user' => $user,
            'pet' => petState($pdo, $userId),
            'stats' => userStats($pdo, $userId),
            'history' => $history->fetchAll(),
            'answer_history' => $answerHistory->fetchAll(),
            'games' => games(),
            'characters' => characters(),
            'companions' => companionCatalog(),
            'achievements' => achievementsFor($pdo, $userId),
            'leaderboard' => studentLeaderboard($pdo),
            'unlocked_difficulties' => ['easy', ...($completedRuns >= 1 ? ['medium'] : []), ...($completedRuns >= 3 ? ['hard'] : [])],
            'correct_total' => (int) $correctStatement->fetchColumn(),
            'performance' => $performanceStatement->fetchAll(),
        ]);
    }

    if ($action === 'pet_state') {
        $user = currentUser($pdo);
        respond(['pet' => petState($pdo, (int) $user['id'])]);
    }

    if ($action === 'register') {
        requireMethod('POST');
        requireCsrf();
        $data = input();
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['role'] ?? 'student');
        $accessibilityValue = $data['accessibility_enabled'] ?? false;
        if (!is_bool($accessibilityValue)) {
            respond(['error' => 'Escolha uma preferência de acessibilidade válida.'], 422);
        }
        $accessibilityEnabled = $accessibilityValue;
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($nameLength < 2 || $nameLength > 60) {
            respond(['error' => 'Seu nome precisa ter entre 2 e 60 caracteres.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            respond(['error' => 'Digite um e-mail válido.'], 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            respond(['error' => 'A senha precisa ter entre 8 e 72 caracteres.'], 422);
        }
        if (!in_array($role, ['student', 'teacher'], true)) {
            respond(['error' => 'Escolha um perfil válido.'], 422);
        }
        $statement = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, accessibility_enabled, font_size)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        try {
            $statement->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                (int) $accessibilityEnabled,
                $accessibilityEnabled ? 'large' : 'normal',
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                respond(['error' => 'Já existe uma conta com este e-mail.'], 409);
            }
            throw $exception;
        }
        $userId = (int) $pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        logSessionEvent('registration_success');
        respond(['message' => 'Conta criada! Sua aventura começa agora.']);
    }

    if ($action === 'login') {
        requireMethod('POST');
        requireCsrf();
        $data = input();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(['error' => 'E-mail ou senha incorretos.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        logSessionEvent('login_success');
        respond(['message' => 'Que bom ter você de volta!']);
    }

    if ($action === 'logout') {
        requireMethod('POST');
        requireCsrf();
        $_SESSION = [];
        session_destroy();
        logSessionEvent('logout');
        respond(['message' => 'Você saiu da sua conta.']);
    }

    requireMethod('POST');
    requireCsrf();
    $user = currentUser($pdo);
    $userId = (int) $user['id'];
    $data = input();

    if ($action === 'save_accessibility') {
        $accessibilityEnabled = $data['accessibility_enabled'] ?? null;
        $fontSize = $data['font_size'] ?? '';
        if (!is_bool($accessibilityEnabled) || !in_array($fontSize, ['small', 'normal', 'large'], true)) {
            respond(['error' => 'Escolha preferências de acessibilidade válidas.'], 422);
        }
        $statement = $pdo->prepare('
            UPDATE users SET accessibility_enabled = ?, font_size = ? WHERE id = ?
        ');
        $statement->execute([(int) $accessibilityEnabled, $fontSize, $userId]);
        respond([
            'preferences' => [
                'accessibility_enabled' => (int) $accessibilityEnabled,
                'font_size' => $fontSize,
            ],
            'message' => 'Preferências de acessibilidade salvas.',
        ]);
    }

    if ($action === 'set_pet_name') {
        $name = trim((string) ($data['name'] ?? ''));
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($nameLength < 1 || $nameLength > 24) {
            respond(['error' => 'O nome do personagem precisa ter entre 1 e 24 caracteres.'], 422);
        }
        $statement = $pdo->prepare('UPDATE users SET pet_name = ? WHERE id = ?');
        $statement->execute([$name, $userId]);
        respond(['pet' => petState($pdo, $userId), 'message' => 'Nome do personagem atualizado!']);
    }

    if ($action === 'pet_affection') {
        $statement = $pdo->prepare('UPDATE users SET affection_count = affection_count + 1 WHERE id = ?');
        $statement->execute([$userId]);
        respond(['pet' => petState($pdo, $userId), 'message' => 'Seu personagem adorou o carinho!']);
    }

    if ($action === 'start_game') {
        $game = (string) ($data['game'] ?? '');
        $difficulty = (string) ($data['difficulty'] ?? 'easy');
        if (!in_array($game, array_column(games(), 'id'), true)) {
            respond(['error' => 'Escolha um jogo válido.'], 422);
        }
        if ($game === 'pro') {
            $difficulty = 'hard';
        }
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            respond(['error' => 'Escolha um nível válido.'], 422);
        }
        $statement = $pdo->prepare("SELECT COUNT(*) FROM game_runs WHERE user_id = ? AND status = 'completed'");
        $statement->execute([$userId]);
        $completedRuns = (int) $statement->fetchColumn();
        $requiredRuns = ['easy' => 0, 'medium' => 1, 'hard' => 3][$difficulty];
        if ($completedRuns < $requiredRuns) {
            respond(['error' => $game === 'pro'
                ? 'Conclua 3 fases para desbloquear o Modo PRO.'
                : 'Conclua mais fases para desbloquear esse nível.'], 403);
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare('UPDATE users SET energy = 100, last_decay_time = CURRENT_TIMESTAMP WHERE id = ?');
        $statement->execute([$userId]);
        $statement = $pdo->prepare('INSERT INTO game_runs (user_id, game_key, difficulty) VALUES (?, ?, ?)');
        $statement->execute([$userId, $game, $difficulty]);
        $runId = (int) $pdo->lastInsertId();
        $pdo->commit();
        $_SESSION['active_run_id'] = $runId;
        unset($_SESSION['active_question']);
        respond(['run_id' => $_SESSION['active_run_id'], 'pet' => petState($pdo, $userId)]);
    }

    if ($action === 'next_question') {
        $runId = (int) ($_SESSION['active_run_id'] ?? 0);
        $run = runById($pdo, $runId, $userId);
        $statement = $pdo->prepare('SELECT total_questions FROM game_runs WHERE id = ?');
        $statement->execute([$runId]);
        $answered = (int) $statement->fetchColumn();
        if ($answered >= 10) {
            respond(['error' => 'Esta fase já terminou.'], 409);
        }
        $question = makeQuestion($run['game_key'], $run['difficulty'], $answered);
        $_SESSION['active_question'] = $question + ['run_id' => $runId];
        respond([
            'stage' => $answered + 1,
            'total_stages' => 10,
            'prompt' => $question['prompt'],
            'hint' => $question['hint'],
            'choices' => $question['choices'],
            'sequence' => $question['sequence'] ?? null,
        ]);
    }

    if ($action === 'answer') {
        $runId = (int) ($_SESSION['active_run_id'] ?? 0);
        $run = runById($pdo, $runId, $userId);
        $question = $_SESSION['active_question'] ?? null;
        if (!is_array($question) || $question['run_id'] !== $runId) {
            respond(['error' => 'Carregue uma pergunta antes de responder.'], 409);
        }
        $submitted = trim((string) ($data['answer'] ?? ''));
        if ($submitted === '' || strlen($submitted) > 30) {
            respond(['error' => 'Digite uma resposta válida.'], 422);
        }
        $correct = answerMatches($submitted, $question['answer']);
        $pdo->beginTransaction();
        $statement = $pdo->prepare("
            INSERT INTO answers (run_id, question_text, expected_answer, submitted_answer, is_correct, explanation)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([$runId, $question['prompt'], $question['answer'], $submitted, (int) $correct, $question['explanation']]);
        $newCorrectCount = (int) $run['correct_count'] + (int) $correct;
        $newQuestionCount = (int) $run['total_questions'] + 1;
        $newScore = (int) $run['score'] + ($correct ? 10 : 0);
        $finished = $newQuestionCount === 10;
        $proRun = $run['game_key'] === 'pro';
        $advancedBridge = $run['game_key'] === 'ponte' && $run['difficulty'] === 'hard';
        $mediumFractions = $run['game_key'] === 'fracoes' && $run['difficulty'] === 'medium';
        $answerCoins = $correct && !$proRun ? ($advancedBridge ? 10 : 5) : 0;
        $completionBonus = $finished
            ? ($proRun
                ? ($newCorrectCount === 10 ? 200 : ($newCorrectCount > 5 ? 100 : 0))
                : (($advancedBridge || $mediumFractions) ? 20 : 10))
            : 0;
        $coinsEarned = $answerCoins + $completionBonus;
        $statement = $pdo->prepare("
            UPDATE game_runs
            SET correct_count = ?, total_questions = ?, score = ?,
                status = ?, completed_at = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE completed_at END
            WHERE id = ?
        ");
        $statement->execute([$newCorrectCount, $newQuestionCount, $newScore, $finished ? 'completed' : 'in_progress', (int) $finished, $runId]);
        $statement = $pdo->prepare('UPDATE users SET coins = coins + ?, total_points = total_points + ? WHERE id = ?');
        $statement->execute([$coinsEarned, $correct ? 10 : 0, $userId]);
        $statement = $pdo->prepare('SELECT total_points FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $characterProgress = synchronizeCharacters($pdo, $userId, (int) $statement->fetchColumn());
        if ($finished && $newQuestionCount === $newCorrectCount) {
            unlockAchievement($pdo, $userId, 'perfect_run');
        }
        if ($finished) {
            unlockAchievement($pdo, $userId, 'first_run');
        }
        $totalCorrect = $pdo->prepare("
            SELECT COALESCE(SUM(correct_count), 0) FROM game_runs
            WHERE user_id = ? AND status = 'completed'
        ");
        $totalCorrect->execute([$userId]);
        if ((int) $totalCorrect->fetchColumn() + ($finished ? 0 : $newCorrectCount) >= 10) {
            unlockAchievement($pdo, $userId, 'ten_correct');
        }
        $pdo->commit();
        unset($_SESSION['active_question']);
        $statement = $pdo->prepare('SELECT coins, total_points FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $updatedUser = $statement->fetch();
        $result = [
            'correct' => $correct,
            'expected_answer' => $question['answer'],
            'explanation' => $question['explanation'],
            'score' => $newScore,
            'correct_count' => $newCorrectCount,
            'total_questions' => $newQuestionCount,
            'finished' => $finished,
            'coins_earned' => $coinsEarned,
            'answer_coins' => $answerCoins,
            'completion_bonus' => $completionBonus,
            'coins' => (int) $updatedUser['coins'],
            'total_points' => (int) $updatedUser['total_points'],
            'character_progress' => $characterProgress,
        ];
        if ($finished) {
            $result['achievements'] = achievementsFor($pdo, $userId);
            $result['stats'] = userStats($pdo, $userId);
            unset($_SESSION['active_run_id']);
        }
        $result['leaderboard'] = studentLeaderboard($pdo);
        respond($result);
    }

    if ($action === 'set_active_character') {
        $characterId = (string) ($data['character_id'] ?? '');
        $character = null;
        foreach (characters() as $candidate) {
            if ($candidate['id'] === $characterId) {
                $character = $candidate;
                break;
            }
        }
        if ($character === null) {
            respond(['error' => 'Escolha uma transformação válida.'], 422);
        }
        $progress = synchronizeCharacters($pdo, $userId);
        if (!in_array($characterId, $progress['unlocked_characters'], true)) {
            respond(['error' => 'Continue jogando para desbloquear essa transformação.'], 403);
        }
        $statement = $pdo->prepare('UPDATE users SET active_character = ? WHERE id = ?');
        $statement->execute([$characterId, $userId]);
        respond([
            'message' => 'Seu personagem agora é ' . $character['name'] . '!',
            'pet' => petState($pdo, $userId),
        ]);
    }

    if ($action === 'buy_companion') {
        requireMethod('POST');
        requireCsrf();
        $data = input();
        $companionId = (string) ($data['companion_id'] ?? '');
        $companion = null;
        foreach (companionCatalog() as $candidate) {
            if ($candidate['id'] === $companionId) {
                $companion = $candidate;
                break;
            }
        }
        if ($companion === null) {
            respond(['error' => 'Escolha um companion válido.'], 422);
        }

        $statement = $pdo->prepare('SELECT coins, owned_companions FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $buyer = $statement->fetch();
        $ownedCompanions = json_decode((string) $buyer['owned_companions'], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($ownedCompanions)) {
            throw new RuntimeException('A lista de companions está inválida.');
        }
        if (in_array($companionId, $ownedCompanions, true)) {
            respond(['error' => 'Você já tem este companion.'], 409);
        }
        if ((int) $buyer['coins'] < (int) $companion['price']) {
            respond(['error' => 'Moedas insuficientes para este companion.'], 409);
        }

        $ownedCompanions[] = $companionId;
        $pdo->beginTransaction();
        $statement = $pdo->prepare('
            UPDATE users SET coins = coins - ?, owned_companions = ?
            WHERE id = ? AND coins >= ?
        ');
        $statement->execute([
            (int) $companion['price'],
            json_encode($ownedCompanions, JSON_THROW_ON_ERROR),
            $userId,
            (int) $companion['price'],
        ]);
        if ($statement->rowCount() !== 1) {
            $pdo->rollBack();
            respond(['error' => 'Seu saldo mudou. Atualize e tente novamente.'], 409);
        }
        $pdo->commit();

        $statement = $pdo->prepare('SELECT coins FROM users WHERE id = ?');
        $statement->execute([$userId]);
        respond([
            'message' => $companion['message'],
            'coins' => (int) $statement->fetchColumn(),
            'pet' => petState($pdo, $userId),
        ]);
    }

    if ($action === 'equip_companion') {
        requireMethod('POST');
        requireCsrf();
        $data = input();
        $companionId = (string) ($data['companion_id'] ?? '');
        $companion = null;
        foreach (companionCatalog() as $candidate) {
            if ($candidate['id'] === $companionId) {
                $companion = $candidate;
                break;
            }
        }
        if ($companion === null) {
            respond(['error' => 'Escolha um companion válido.'], 422);
        }
        $statement = $pdo->prepare('SELECT owned_companions FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $ownedCompanions = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($ownedCompanions) || !in_array($companionId, $ownedCompanions, true)) {
            respond(['error' => 'Compre este companion antes de equipá-lo.'], 403);
        }
        $statement = $pdo->prepare('UPDATE users SET equipped_companion_id = ? WHERE id = ?');
        $statement->execute([$companionId, $userId]);
        respond([
            'message' => $companion['name'] . ' está ao seu lado!',
            'pet' => petState($pdo, $userId),
        ]);
    }

    respond(['error' => 'Ação não encontrada.'], 404);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log((string) $exception);
    if ($exception instanceof PDOException) {
        logSessionEvent('database_error', [
            'sql_state' => (string) ($exception->errorInfo[0] ?? $exception->getCode()),
            'driver_code' => $exception->errorInfo[1] ?? null,
            'driver_message' => (string) ($exception->errorInfo[2] ?? ''),
        ]);
        respond(['error' => 'Não foi possível acessar os dados. Confira as credenciais do MySQL e se o arquivo banco.sql foi importado no banco configurado.'], 500);
    }
    respond(['error' => 'Ocorreu um erro inesperado. Tente novamente.'], 500);
}
