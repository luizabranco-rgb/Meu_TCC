const state = {
    csrf: '',
    user: null,
    pet: null,
    games: [],
    characters: [],
    companions: [],
    leaderboard: [],
    performance: [],
    difficulty: 'easy',
    unlockedDifficulties: ['easy'],
    currentGame: null,
    currentQuestion: null,
    score: 0,
    correctCount: 0,
    coinsEarned: 0,
    finished: false,
    registration: false,
    audioContext: null,
    musicEnabled: localStorage.getItem('mathplayMusic') !== 'false',
    musicTimer: null,
    musicStep: 0,
    selectedBridgeAnswer: null,
    gameTimer: null,
    gameStartedAt: null,
    displayedCharacterId: null,
    transformEffectTimer: null,
    pendingCharacterTransform: false,
    pendingCelebrations: [],
    accessibilityDialogReturnFocus: null,
};

const $ = (selector) => document.querySelector(selector);
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

async function request(action, data = null) {
    const options = {
        method: data === null ? 'GET' : 'POST',
        headers: { Accept: 'application/json' },
    };
    if (data !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-Token'] = state.csrf;
        options.body = JSON.stringify(data);
    }
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error('Não foi possível entender a resposta do servidor. Tente novamente.');
    }
    if (!response.ok) {
        throw new Error(payload.error || 'Não foi possível concluir esta ação.');
    }
    return payload;
}

function showToast(message) {
    const toast = $('#toast');
    toast.textContent = message;
    toast.classList.remove('is-transformation');
    toast.classList.add('is-visible');
    clearTimeout(showToast.timeout);
    showToast.timeout = setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

function showTransformationMessage(character) {
    const messages = {
        turtle: 'Vagarosamente, mas com passos firmes! Você agora é uma tartaruga sábia. Mais defesa para os desafios! ✨',
        fox: 'Rápido e esperto como uma raposa! Você acabou de se transformar. Use sua inteligência para dominar o jogo! 🦊💡',
        panda: 'Ohh, fofura extrema ativada! Você agora é um pandinha. Relaxe um pouco... mas continue ganhando pontos! 🐼❤️',
        unicorn: 'MAGIA PURA! Você alcançou o nível lendário e se tornou um UNICÓRNIO! O jogo agora é seu para brilhar! 🦄✨🌈',
    };
    const message = messages[character.id] || `${character.name} desbloqueado! Sua nova forma chegou para viver muitas aventuras com você! ${character.icon}`;
    queueCelebration({
        title: 'Transformação desbloqueada!',
        name: character.name,
        icon: character.icon,
        message,
        kind: 'character',
    });
}

function showCompanionUnlock(companion) {
    queueCelebration({
        title: 'Novo amiguinho desbloqueado!',
        name: companion.name,
        icon: companion.icon,
        message: companion.message,
        kind: 'companion',
    });
}

function queueCelebration(celebration) {
    state.pendingCelebrations.push(celebration);
    showNextCelebration();
}

function showNextCelebration() {
    const dialog = $('#transformation-dialog');
    if (dialog.open || state.pendingCelebrations.length === 0) return;
    const celebration = state.pendingCelebrations.shift();
    $('#transformation-dialog').dataset.kind = celebration.kind;
    $('#transformation-title').textContent = celebration.title;
    $('#transformation-character-icon').textContent = celebration.icon;
    $('#transformation-character-name').textContent = celebration.name;
    $('#transformation-message').textContent = celebration.message;
    const particles = $('#celebration-particles');
    particles.replaceChildren(...['✦', '♥', '✧', '✦', '♥', '✧', '✧', '✦'].map((symbol, index) => {
        const particle = document.createElement('span');
        particle.className = 'celebration-particle';
        particle.style.setProperty('--particle-index', String(index));
        particle.textContent = symbol;
        return particle;
    }));
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
        dialog.classList.add('is-open');
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
    }
    try {
        playTransformationSound();
    } catch (error) {
        console.warn('O som da celebração não pôde ser reproduzido.', error);
    }
    $('#celebration-continue').focus();
}

function setAuthMessage(message = '') {
    const element = $('#auth-message');
    element.textContent = message;
    element.hidden = !message;
}

function applyAccessibilitySettings(user) {
    const highContrast = Number(user.accessibility_enabled) === 1;
    const fontSize = ['small', 'normal', 'large'].includes(user.font_size) ? user.font_size : 'normal';
    document.body.classList.toggle('accessibility-high-contrast', highContrast);
    document.body.dataset.fontSize = fontSize;
}

function openAccessibilityDialog() {
    if (!state.user) return;
    state.accessibilityDialogReturnFocus = document.activeElement;
    $('#accessibility-contrast-input').checked = Number(state.user.accessibility_enabled) === 1;
    const fontSize = ['small', 'normal', 'large'].includes(state.user.font_size) ? state.user.font_size : 'normal';
    document.querySelectorAll('#accessibility-form input[name="font_size"]').forEach((input) => {
        input.checked = input.value === fontSize;
    });
    $('#accessibility-message').hidden = true;
    const dialog = $('#accessibility-dialog');
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
        dialog.classList.add('is-open');
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
    }
    $('#accessibility-contrast-input').focus();
}

function closeAccessibilityDialog() {
    const dialog = $('#accessibility-dialog');
    if (typeof dialog.close === 'function') {
        dialog.close();
        return;
    }
    dialog.removeAttribute('open');
    dialog.classList.remove('is-open');
    dialog.removeAttribute('aria-modal');
    state.accessibilityDialogReturnFocus?.focus();
    state.accessibilityDialogReturnFocus = null;
}

function setAuthMode(registering) {
    state.registration = registering;
    $('#login-tab').classList.toggle('is-active', !registering);
    $('#login-tab').setAttribute('aria-selected', String(!registering));
    $('#register-tab').classList.toggle('is-active', registering);
    $('#register-tab').setAttribute('aria-selected', String(registering));
    $('#auth-title').textContent = registering ? 'Vamos criar sua conta!' : 'Que bom ter você!';
    $('#auth-subtitle').textContent = registering
        ? 'Prepare-se para começar uma aventura incrível.'
        : 'Entre para continuar sua aventura.';
    $('#name-field').hidden = !registering;
    $('#role-field').hidden = !registering;
    $('#accessibility-signup-field').hidden = !registering;
    $('#auth-form').elements.name.required = registering;
    $('#auth-form').elements.password.autocomplete = registering ? 'new-password' : 'current-password';
    $('#auth-submit').innerHTML = registering
        ? 'Criar minha conta <span>→</span>'
        : 'Entrar na aventura <span>→</span>';
    setAuthMessage();
}

function enterApp() {
    applyAccessibilitySettings(state.user);
    $('#auth-screen').hidden = true;
    $('#app-screen').hidden = false;
    $('#main-nav').hidden = false;
    $('#student-name').textContent = state.user.name.split(' ')[0];
    $('#stat-points').textContent = Number(state.user.total_points).toLocaleString('pt-BR');
    $('#stat-coins').textContent = Number(state.user.coins).toLocaleString('pt-BR');
    $('#stat-games').textContent = Number(state.stats.games_played).toLocaleString('pt-BR');
    $('#stat-accuracy').textContent = `${state.stats.accuracy}%`;
    $('#answer-input').placeholder = 'Ou digite sua resposta...';
    renderDifficulties();
    renderGames();
    renderAchievements();
    renderHistory();
    renderAnswerHistory();
    renderPerformance();
    renderLeaderboard();
    renderPet();
    if (!state.pet?.name) openPetNameDialog(true);
}

function showAuth() {
    state.user = null;
    state.pet = null;
    document.body.classList.remove('accessibility-high-contrast');
    document.body.dataset.fontSize = 'normal';
    if ($('#pet-name-dialog').open) $('#pet-name-dialog').close();
    $('#auth-screen').hidden = false;
    $('#app-screen').hidden = true;
    $('#main-nav').hidden = true;
    setAuthMode(false);
}

function renderPet() {
    if (!state.pet) return;
    const energy = Math.max(0, Math.min(100, Number(state.pet.energy) || 0));
    const character = state.characters.find((item) => item.id === state.pet.active_character) || state.characters[0];
    if (state.displayedCharacterId !== null && state.displayedCharacterId !== character.id) {
        if ($('#dashboard-view').hidden) {
            state.pendingCharacterTransform = true;
        } else {
            triggerCharacterTransform();
        }
    }
    state.displayedCharacterId = character.id;
    $('#pet-name-display').textContent = state.pet.name || 'Sem nome';
    $('#pet-energy-label').textContent = `${energy}%`;
    $('#pet-energy-fill').style.width = `${energy}%`;
    $('.pet-energy-track').setAttribute('aria-valuenow', String(energy));
    $('#pet-affection-count').textContent = `${Number(state.pet.affection_count)} ${Number(state.pet.affection_count) === 1 ? 'carinho' : 'carinhos'}`;
    $('#pet-rename-button').textContent = 'Editar nome';
    $('#avatar-character').setAttribute('aria-label', `Seu personagem ${character?.name || 'Coelho'}`);
    $('#avatar-character').dataset.character = character?.id || 'rabbit';
    $('#avatar-character-caption').textContent = `${character?.icon || '🐰'} ${character?.name || 'Coelho'} · sua forma atual`;
    $('#hero-active-character').textContent = character?.icon || '🐰';
    $('#hero-active-character').dataset.character = character?.id || 'rabbit';
    $('#character-points').textContent = Number(state.user.total_points).toLocaleString('pt-BR');
    const isRabbit = character?.id === 'rabbit';
    $('#avatar-rabbit-art').hidden = !isRabbit;
    $('#avatar-transformed-icon').hidden = isRabbit;
    $('#avatar-transformed-icon').textContent = isRabbit ? '' : character.icon;
    $('#avatar-character').classList.toggle('is-transformed', !isRabbit);
    const equippedCompanion = state.companions.find((item) => item.id === state.pet.equipped_companion_id);
    $('#equipped-companion').hidden = !equippedCompanion;
    if (equippedCompanion) {
        $('#equipped-companion').setAttribute('aria-label', `${equippedCompanion.name} está ao lado do seu personagem`);
        $('#equipped-companion-icon').textContent = equippedCompanion.icon;
        $('#equipped-companion-name').textContent = equippedCompanion.name;
        $('#equipped-companion').classList.toggle('is-legendary', equippedCompanion.rarity === 'Lendário');
        $('#equipped-companion-gear').textContent = equippedCompanion.equipment || '';
        $('#equipped-companion-gear').hidden = !equippedCompanion.equipment;
    } else {
        $('#equipped-companion').classList.remove('is-legendary');
    }
    if (state.pendingCharacterTransform && !$('#dashboard-view').hidden) {
        state.pendingCharacterTransform = false;
        triggerCharacterTransform();
    }
    renderCharacters();
    renderCompanions();
}

function triggerCharacterTransform() {
    window.clearTimeout(state.transformEffectTimer);
    const particleVectors = [
        ['✦', -72, -62], ['♥', 64, -67], ['✧', -88, 2],
        ['✦', 83, -4], ['♥', -61, 70], ['✧', 66, 68],
    ];
    const targets = [
        [$('#avatar-character'), $('#character-transform-particles')],
        [$('#hero-active-character'), $('#hero-transform-particles')],
    ];
    targets.forEach(([character, particles]) => {
        character.classList.remove('is-transforming');
        particles.replaceChildren(...particleVectors.map(([symbol, x, y], index) => {
            const particle = document.createElement('span');
            particle.className = 'transform-particle';
            particle.style.setProperty('--particle-x', `${x}px`);
            particle.style.setProperty('--particle-y', `${y}px`);
            particle.style.setProperty('--particle-index', String(index));
            particle.textContent = symbol;
            return particle;
        }));
        particles.hidden = false;
        void character.offsetWidth;
        character.classList.add('is-transforming');
    });
    state.transformEffectTimer = window.setTimeout(() => {
        targets.forEach(([character, particles]) => {
            character.classList.remove('is-transforming');
            particles.hidden = true;
            particles.replaceChildren();
        });
    }, 1250);
}

function openPetNameDialog(required = false) {
    const dialog = $('#pet-name-dialog');
    if (dialog.open) return;
    $('#pet-name-input').value = state.pet?.name || '';
    $('#pet-name-error').hidden = true;
    $('#pet-name-cancel').hidden = required;
    dialog.showModal();
    $('#pet-name-input').focus();
}

function renderDifficulties() {
    document.querySelectorAll('.difficulty-button').forEach((button) => {
        const unlocked = state.unlockedDifficulties.includes(button.dataset.difficulty);
        button.disabled = !unlocked;
        button.classList.toggle('is-locked', !unlocked);
        button.classList.toggle('is-selected', state.difficulty === button.dataset.difficulty);
        const lock = button.querySelector('.lock-mark');
        if (lock) lock.hidden = unlocked;
    });
    const levelNames = { easy: 'Fácil', medium: 'Médio', hard: 'Difícil / Avançado' };
    const currentLevel = state.unlockedDifficulties.at(-1);
    $('#current-level').textContent = levelNames[currentLevel];
    const required = state.difficulty === 'medium' ? 1 : state.difficulty === 'hard' ? 3 : 0;
    const note = state.difficulty === 'easy'
        ? 'Cada fase tem 10 desafios. Acerte para ganhar pontos e moedas!'
        : state.difficulty === 'hard'
            ? 'Desbloqueado após 3 fases: os jogos trazem números maiores e desafios um pouco mais elaborados; a Ponte inclui padrões avançados. O Modo PRO continua sendo o desafio especial.'
            : `Nível Médio: conclua ${required} fase para desbloquear. Os jogos trazem números um pouco maiores e novas etapas, sem saltos grandes de dificuldade.`;
    $('#difficulty-note').innerHTML = `<span>Seu nível atual: <strong id="current-level">${levelNames[currentLevel]}</strong></span> · ${note}`;
}

function renderGames() {
    const proUnlocked = state.unlockedDifficulties.includes('hard');
    $('#game-grid').innerHTML = state.games.map((game, index) => `
        <article class="game-card game-card-${escapeHtml(game.color)} ${game.id === 'pro' ? 'is-pro-card' : ''}">
            <div class="game-card-top">
                <span class="game-icon">${escapeHtml(game.icon)}</span>
                ${game.id === 'pro'
                    ? '<span class="pro-badge">✦ MODO PRO ✦</span>'
                    : `<span class="game-number">0${index + 1}</span>`}
            </div>
            <div>
                <span class="game-topic">${escapeHtml(game.topic)}</span>
                <h3>${escapeHtml(game.title)}</h3>
            </div>
            <div class="game-card-bottom">
                <span>${game.id === 'pro'
                    ? '6+ acertos: 100 moedas · gabarito: 200'
                    : game.id === 'fracoes'
                        ? 'No Médio: denominadores diferentes · +10 moedas bônus'
                        : '10 desafios · moedas a cada acerto'}</span>
                <button type="button" class="play-button ${game.id === 'pro' ? 'pro-play-button' : ''}" data-play="${escapeHtml(game.id)}" aria-label="Jogar ${escapeHtml(game.title)}" ${game.id === 'pro' && !proUnlocked ? 'disabled title="Conclua 3 fases para desbloquear"' : ''}>${game.id === 'pro' && !proUnlocked ? '🔒 3 fases' : 'Jogar'} <span>→</span></button>
            </div>
        </article>
    `).join('');
}

const studyGuides = {
    inteiros: { title: 'Operações com números inteiros', advice: 'Atenção às regras de sinais: sinais iguais na multiplicação e divisão dão resultado positivo; sinais diferentes, negativo.', tip: 'Na adição, represente os números na reta: somar um negativo é caminhar para a esquerda.' },
    fracoes: { title: 'Frações e operações', advice: 'Pratique encontrar denominadores comuns antes de somar ou subtrair frações.', tip: 'Multiplique numerador e denominador pelo mesmo número para criar frações equivalentes.' },
    equacoes: { title: 'Equações de 1º grau', advice: 'Revise como desfazer as operações para deixar o x sozinho, mantendo o equilíbrio dos dois lados.', tip: 'Faça a operação inversa nos dois lados da igualdade e confira substituindo o resultado.' },
    geometria: { title: 'Área e perímetro', advice: 'Diferencie a medida do contorno (perímetro) da medida da superfície (área).', tip: 'Perímetro soma os lados; área do retângulo multiplica base pela altura.' },
    corrida: { title: 'Multiplicação e tabuadas', advice: 'Pratique as tabuadas e use a multiplicação como adição de grupos iguais.', tip: 'Separe um fator: 7 × 8 pode ser 7 × (5 + 3).' },
    codigo: { title: 'Cálculo mental e multiplicação', advice: 'Pratique multiplicações de cabeça separando os fatores em grupos menores.', tip: 'Arredonde um fator e depois compense a diferença no resultado.' },
    ponte: { title: 'Sequências e regularidades', advice: 'Observe as diferenças entre números vizinhos para descobrir a regra da sequência.', tip: 'Subtraia cada termo pelo anterior; diferenças iguais revelam uma sequência aditiva.' },
    pro: { title: 'Desafios avançados de matemática', advice: 'Revise cada etapa antes de calcular: monte a equação, identifique as relações entre as grandezas e confira se a resposta faz sentido no contexto.', tip: 'Em porcentagens sucessivas, calcule cada taxa sobre o valor atualizado; em geometria, desenhe a figura e anote as medidas conhecidas.' },
};

function renderPerformance(performance = null) {
    if (performance) state.performance = performance;
    const rows = [...(state.performance || [])].sort((left, right) =>
        (Number(right.incorrect) / Math.max(1, Number(right.attempted)))
            - (Number(left.incorrect) / Math.max(1, Number(left.attempted)))
        || Number(right.incorrect) - Number(left.incorrect)
    );
    const attempted = rows.reduce((total, row) => total + Number(row.attempted), 0);
    const incorrect = rows.reduce((total, row) => total + Number(row.incorrect), 0);
    const summary = $('#performance-summary');
    const list = $('#performance-list');
    const highlights = $('#performance-highlights');
    const recommendations = $('#study-now-content');
    const mistakes = $('#performance-mistakes');
    const recentMistakes = (state.answerHistory || []).filter((answer) => !Number(answer.is_correct));
    if (!attempted) {
        summary.innerHTML = '<div class="performance-empty">Ainda não há respostas registradas. Faça uma aventura para descobrir seus pontos fortes e receber dicas de estudo. 🌱</div>';
        highlights.innerHTML = '<article class="performance-highlight"><span>🌱</span><div><strong>Ponto forte</strong><p>Você está começando agora — cada desafio vai revelar novas habilidades.</p></div></article><article class="performance-highlight improvement-highlight"><span>🧩</span><div><strong>Área de melhoria principal</strong><p>Responda algumas questões para descobrir qual assunto merece mais atenção.</p></div></article>';
        recommendations.innerHTML = '<p>Jogue uma fase para receber uma recomendação baseada nas suas respostas recentes.</p>';
        list.replaceChildren();
        mistakes.innerHTML = '<p class="performance-empty">Suas questões erradas vão aparecer aqui com uma explicação passo a passo. Você consegue! ✨</p>';
        return;
    }
    const accuracy = Math.round((attempted - incorrect) * 100 / attempted);
    const strongest = [...rows].sort((left, right) =>
        (Number(right.correct) / Math.max(1, Number(right.attempted)))
            - (Number(left.correct) / Math.max(1, Number(left.attempted)))
        || Number(right.attempted) - Number(left.attempted)
    )[0];
    const needsWork = rows.find((row) => Number(row.incorrect) > 0) || rows[0];
    const strongRate = Math.round(Number(strongest.correct) * 100 / Math.max(1, Number(strongest.attempted)));
    const focusRate = Math.round(Number(needsWork.correct) * 100 / Math.max(1, Number(needsWork.attempted)));
    const recentFocus = recentMistakes[0];
    const guide = studyGuides[recentFocus?.game_key || needsWork.game_key] || studyGuides[needsWork.game_key];
    summary.innerHTML = `
        <div class="performance-stat"><span>Questões respondidas</span><strong>${attempted}</strong></div>
        <div class="performance-stat"><span>Aproveitamento geral</span><strong>${accuracy}%</strong></div>
        <div class="performance-stat"><span>Acertos</span><strong>${attempted - incorrect}</strong></div>
        <div class="performance-stat"><span>Para revisar</span><strong>${incorrect}</strong></div>
    `;
    highlights.innerHTML = `
        <article class="performance-highlight">
            <span aria-hidden="true">🌟</span>
            <div><strong>Ponto forte</strong><p>${strongRate > 0
                ? `${escapeHtml(studyGuides[strongest.game_key]?.title || 'Matemática')} · ${strongRate}% de acertos`
                : `Você já começou a praticar ${escapeHtml(studyGuides[strongest.game_key]?.title || 'Matemática')}. Cada tentativa ajuda a construir sua confiança!`}</p></div>
        </article>
        <article class="performance-highlight improvement-highlight">
            <span aria-hidden="true">🎯</span>
            <div><strong>Área de melhoria principal</strong><p>${escapeHtml(studyGuides[needsWork.game_key]?.title || 'Matemática')} · ${focusRate}% de acertos</p></div>
        </article>
    `;
    recommendations.innerHTML = `
        <p>${recentFocus
            ? `Na sua resposta recente sobre <strong>${escapeHtml(guide.title)}</strong>, vamos reforçar esta ideia:`
            : `Você está acertando bastante em <strong>${escapeHtml(guide.title)}</strong>. Continue avançando e revise este ponto:`}</p>
        <strong class="study-now-advice">${escapeHtml(guide.advice)}</strong>
        <span class="study-now-tip">💡 ${escapeHtml(guide.tip)}</span>
        ${recentFocus ? `<span class="study-now-context">Recomendação baseada na sua resposta mais recente: “${escapeHtml(recentFocus.question_text)}”</span>` : ''}
    `;
    list.innerHTML = rows.map((row, index) => {
        const guide = studyGuides[row.game_key] || { title: 'Matemática', advice: 'Revise os passos das atividades e tente novamente.' };
        const total = Number(row.attempted);
        const wrong = Number(row.incorrect);
        const correct = Number(row.correct);
        const rate = total ? Math.round(correct * 100 / total) : 0;
        const recentRate = Number(row.recent_attempted)
            ? Math.round(Number(row.recent_correct) * 100 / Number(row.recent_attempted))
            : rate;
        const previousRate = Number(row.previous_attempted)
            ? Math.round(Number(row.previous_correct) * 100 / Number(row.previous_attempted))
            : null;
        const trend = previousRate === null ? null : recentRate - previousRate;
        const trendLabel = trend === null
            ? 'Acompanhe sua evolução com mais respostas'
            : trend > 0 ? `Subiu ${trend} pontos nos últimos desafios`
                : trend < 0 ? `Vamos praticar: ${Math.abs(trend)} pontos abaixo do bloco anterior`
                    : 'Seu aproveitamento se manteve estável';
        return `
            <article class="performance-topic ${index === 0 && wrong ? 'needs-focus' : ''}">
                <div class="performance-topic-heading">
                    <strong>${escapeHtml(guide.title)}</strong>
                    <span class="mastery-value">${rate}% maestria</span>
                </div>
                <div class="mastery-meter" role="progressbar" aria-label="Maestria em ${escapeHtml(guide.title)}" aria-valuenow="${rate}" aria-valuemin="0" aria-valuemax="100">
                    <span class="mastery-correct" style="width:${rate}%"></span><span class="mastery-incorrect" style="width:${100 - rate}%"></span>
                </div>
                <div class="mastery-counts"><span>✓ ${correct} acertos</span><span>↻ ${wrong} ${wrong === 1 ? 'erro' : 'erros'}</span></div>
                <p class="mastery-trend ${trend > 0 ? 'trend-up' : trend < 0 ? 'trend-down' : ''}">${trend > 0 ? '↗' : trend < 0 ? '↘' : '→'} ${escapeHtml(trendLabel)}</p>
            </article>
        `;
    }).join('');
    mistakes.innerHTML = recentMistakes.length
        ? recentMistakes.slice(0, 4).map((answer) => {
            const mistakeGuide = studyGuides[answer.game_key] || { title: 'Matemática' };
            return `
                <article class="diagnostic-mistake">
                    <div class="diagnostic-mistake-heading"><span>✏️ ${escapeHtml(mistakeGuide.title)}</span><time>${escapeHtml(new Date(`${answer.answered_at.replace(' ', 'T')}Z`).toLocaleDateString('pt-BR'))}</time></div>
                    <strong class="diagnostic-question">${escapeHtml(answer.question_text)}</strong>
                    <div class="diagnostic-answers">
                        <span class="diagnostic-student-answer"><small>Sua resposta</small><strong>${escapeHtml(answer.submitted_answer)}</strong></span>
                        <span class="diagnostic-correct-answer"><small>Resposta correta</small><strong>${escapeHtml(answer.expected_answer)}</strong></span>
                    </div>
                    <p><strong>Como pensar:</strong> ${escapeHtml(answer.explanation || 'Revise a dica do desafio e tente identificar cada etapa do cálculo.')}</p>
                </article>
            `;
        }).join('')
        : '<p class="performance-empty">Nenhum erro recente por aqui — ótimo trabalho! Continue praticando para manter essa sequência. 🌟</p>';
}

function renderAchievements(achievements = null) {
    if (achievements) state.achievements = achievements;
    const list = state.achievements || [];
    $('#achievement-list').innerHTML = list.map((item) => `
        <article class="achievement-item ${item.unlocked_at ? 'is-unlocked' : 'is-locked'}">
            <span class="achievement-icon">${escapeHtml(item.icon)}</span>
            <div><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.description)}</span></div>
            <span class="achievement-status">${item.unlocked_at ? '✓' : '🔒'}</span>
        </article>
    `).join('') || '<p class="empty-state">Suas medalhas vão aparecer aqui. Bora conquistar a primeira? ✨</p>';
}

function renderHistory() {
    const history = state.history || [];
    const gameNames = Object.fromEntries(state.games.map((game) => [game.id, game.title]));
    $('#history-list').innerHTML = history.length
        ? history.map((run) => `
            <article class="history-item">
                <span class="history-icon">${escapeHtml(state.games.find((game) => game.id === run.game_key)?.icon || '🎮')}</span>
                <div class="history-details">
                    <strong>${escapeHtml(gameNames[run.game_key] || 'Aventura')}</strong>
                    <span>${Number(run.correct_count)}/${Number(run.total_questions)} acertos · ${escapeHtml(run.difficulty)}</span>
                </div>
                <strong class="history-score">${Number(run.score)} <small>pts</small></strong>
            </article>
        `).join('')
        : '<p class="empty-state">Sua primeira aventura vai aparecer aqui. 🌟</p>';
}

function renderAnswerHistory() {
    const history = state.answerHistory || [];
    const gameNames = Object.fromEntries(state.games.map((game) => [game.id, game.title]));
    $('#answer-history-list').innerHTML = history.length
        ? history.map((answer) => `
            <article class="answer-history-item ${Number(answer.is_correct) ? 'answer-is-correct' : 'answer-is-wrong'}">
                <span class="answer-result-icon" aria-label="${Number(answer.is_correct) ? 'Resposta correta' : 'Resposta incorreta'}">${Number(answer.is_correct) ? '✓' : '×'}</span>
                <div class="answer-history-details">
                    <strong>${escapeHtml(answer.question_text)}</strong>
                    <span>${escapeHtml(gameNames[answer.game_key] || 'Aventura')} · Sua resposta: ${escapeHtml(answer.submitted_answer)}${Number(answer.is_correct) ? ' · Mandou bem!' : ` · Correta: ${escapeHtml(answer.expected_answer)}`}</span>
                    ${!Number(answer.is_correct) && answer.explanation ? `<small>${escapeHtml(answer.explanation)}</small>` : ''}
                </div>
            </article>
        `).join('')
        : '<p class="empty-state">Suas respostas certas e os aprendizados de cada desafio vão aparecer aqui. ✨</p>';
}

function renderLeaderboard(leaderboard = null) {
    if (leaderboard) state.leaderboard = leaderboard;
    const rows = state.leaderboard || [];
    const students = rows;
    const topThree = students.filter((row) => Number(row.position) <= 3);
    const remainder = students.filter((row) => Number(row.position) > 3);
    const badges = ['👑', '🥈', '🥉'];

    $('#leaderboard-podium').innerHTML = topThree.length
        ? `<div class="podium-grid">${topThree.map((row) => `
            <article class="podium-card podium-place-${Number(row.position)} ${Number(row.id) === Number(state.user.id) ? 'is-you' : ''}">
                <span class="podium-medal">${badges[Number(row.position) - 1]}</span>
                <span class="podium-avatar">${escapeHtml(String(row.name).trim().charAt(0).toUpperCase())}</span>
                <strong>${escapeHtml(row.name)}${Number(row.id) === Number(state.user.id) ? ' <span class="you-label">VOCÊ</span>' : ''}</strong>
                <span class="podium-points">✨ ${Number(row.total_points).toLocaleString('pt-BR')} pontos</span>
                <span class="podium-base">${Number(row.position)}</span>
            </article>
        `).join('')}</div>`
        : '<p class="empty-state">O ranking começa com a primeira aventura dos estudantes. Seja a primeira pessoa a aparecer por aqui! ✨</p>';

    $('#leaderboard-list').innerHTML = remainder.map((row) => `
        <article class="leaderboard-row ${Number(row.id) === Number(state.user.id) ? 'is-you' : ''}">
            <span class="leaderboard-position">${Number(row.position)}º</span>
            <span class="leaderboard-avatar">${escapeHtml(String(row.name).trim().charAt(0).toUpperCase())}</span>
            <span class="leaderboard-name">${escapeHtml(row.name)}${Number(row.id) === Number(state.user.id) ? ' <span class="you-label">VOCÊ</span>' : ''}</span>
            <span class="leaderboard-progress"><span style="width:${Math.min(100, Number(row.total_points) / Math.max(1, Number(students[0]?.total_points || 1)) * 100)}%"></span></span>
            <strong>${Number(row.total_points).toLocaleString('pt-BR')} <small>pts</small></strong>
        </article>
    `).join('');

}

function updateMusicButton() {
    const button = $('#music-toggle');
    const playing = state.musicEnabled && state.audioContext?.state === 'running';
    button.classList.toggle('is-playing', Boolean(playing));
    button.setAttribute('aria-pressed', String(Boolean(playing)));
    button.setAttribute('aria-label', playing ? 'Desativar música ambiente' : 'Ativar música ambiente');
    button.querySelector('.music-label').textContent = playing ? 'Música ligada' : 'Ativar música';
}

function playNote(frequency, duration, { volume = 0.04, type = 'sine', delay = 0 } = {}) {
    const context = state.audioContext;
    if (!context || !state.musicEnabled) return;

    const oscillator = context.createOscillator();
    const envelope = context.createGain();
    const startsAt = context.currentTime + delay;
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, startsAt);
    envelope.gain.setValueAtTime(0.0001, startsAt);
    envelope.gain.exponentialRampToValueAtTime(volume, startsAt + Math.min(0.08, duration / 3));
    envelope.gain.exponentialRampToValueAtTime(0.0001, startsAt + duration);
    oscillator.connect(envelope);
    envelope.connect(context.destination);
    oscillator.start(startsAt);
    oscillator.stop(startsAt + duration + 0.02);
}

function playMusicPhrase() {
    const melody = [523.25, 659.25, 783.99, 659.25, 587.33, 783.99, 880, 783.99, 523.25, 659.25, 783.99, 987.77, 880, 783.99, 659.25, 587.33];
    const note = melody[state.musicStep % melody.length];
    if (state.musicStep % 8 === 0) {
        const chords = [
            [261.63, 329.63, 392],
            [220, 261.63, 329.63],
            [174.61, 220, 261.63],
            [196, 246.94, 293.66],
        ];
        const chord = chords[Math.floor(state.musicStep / 8) % chords.length];
        chord.forEach((frequency) => playNote(frequency, 2.5, { volume: 0.009, type: 'sine' }));
    }
    playNote(note, 0.58, { volume: 0.016, type: 'sine' });
    state.musicStep += 1;
}

async function startMusic() {
    if (!state.musicEnabled) {
        updateMusicButton();
        return;
    }
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
        state.musicEnabled = false;
        localStorage.setItem('mathplayMusic', 'false');
        updateMusicButton();
        showToast('A música ambiente não está disponível neste navegador.');
        return;
    }
    state.audioContext ||= new AudioContextClass();
    if (state.audioContext.state === 'suspended') await state.audioContext.resume();
    if (!state.musicTimer) {
        playMusicPhrase();
        state.musicTimer = window.setInterval(playMusicPhrase, 560);
    }
    updateMusicButton();
}

function playAnswerSound(correct) {
    if (!state.musicEnabled || !state.audioContext || state.audioContext.state !== 'running') return;
    if (correct) {
        playNote(659.25, 0.3, { volume: 0.052, type: 'triangle' });
        playNote(783.99, 0.38, { volume: 0.052, type: 'triangle', delay: 0.11 });
        playNote(1046.5, 0.48, { volume: 0.058, type: 'triangle', delay: 0.23 });
        return;
    }
    playNote(329.63, 0.34, { volume: 0.046, type: 'sine' });
    playNote(277.18, 0.44, { volume: 0.042, type: 'sine', delay: 0.12 });
}

function playTransformationSound() {
    if (!state.musicEnabled || !state.audioContext || state.audioContext.state !== 'running') return;
    playNote(783.99, 0.42, { volume: 0.045, type: 'sine' });
    playNote(987.77, 0.48, { volume: 0.045, type: 'sine', delay: 0.12 });
    playNote(1174.66, 0.62, { volume: 0.05, type: 'triangle', delay: 0.24 });
}

function renderCharacters(characters = null) {
    if (characters) state.characters = characters;
    const unlocked = new Set(state.pet?.unlocked_characters || ['rabbit']);
    const activeCharacter = state.pet?.active_character || 'rabbit';
    $('#character-grid').innerHTML = state.characters.map((character) => {
        const isUnlocked = unlocked.has(character.id);
        const isActive = activeCharacter === character.id;
        const pointsNeeded = Math.max(0, Number(character.threshold) - Number(state.user.total_points));
        return `
            <article class="character-card ${isActive ? 'is-active' : ''} ${isUnlocked ? 'is-unlocked' : 'is-locked'}">
                <span class="character-card-icon" aria-hidden="true">${escapeHtml(character.icon)}</span>
                <div class="character-card-copy">
                    <strong>${escapeHtml(character.name)}</strong>
                    <span>${Number(character.threshold).toLocaleString('pt-BR')} pontos</span>
                </div>
                ${isActive
                    ? '<span class="character-state">Em uso</span>'
                    : isUnlocked
                        ? `<button type="button" class="character-action" data-select-character="${escapeHtml(character.id)}">Transformar</button>`
                        : `<span class="character-locked-note">🔒 Faltam ${pointsNeeded.toLocaleString('pt-BR')} pts</span>`}
            </article>
        `;
    }).join('');
}

function renderCompanions() {
    const catalog = $('#companion-catalog');
    if (!catalog) return;
    $('#companion-coins').textContent = Number(state.user?.coins || 0).toLocaleString('pt-BR');
    const owned = new Set(state.pet?.owned_companions || []);
    const rarityOrder = ['Comum', 'Incomum', 'Raro', 'Épico', 'Lendário'];
    const rarityClasses = {
        Comum: 'common',
        Incomum: 'uncommon',
        Raro: 'rare',
        Épico: 'epic',
        Lendário: 'legendary',
    };
    catalog.innerHTML = rarityOrder.map((rarity) => {
        const companions = state.companions.filter((companion) => companion.rarity === rarity);
        return `
            <section class="companion-rarity-group">
                <div class="companion-rarity-heading">
                    <h3>${escapeHtml(rarity)}</h3>
                    <span>${companions.length} companions</span>
                </div>
                <div class="companion-grid">
                    ${companions.map((companion) => {
                        const isOwned = owned.has(companion.id);
                        const isEquipped = state.pet?.equipped_companion_id === companion.id;
                        const isLegendary = companion.rarity === 'Lendário';
                        const action = isEquipped
                            ? '<span class="companion-equipped-label">Ao seu lado</span>'
                            : isOwned
                                ? `<button type="button" class="companion-action" data-equip-companion="${escapeHtml(companion.id)}">Equipar</button>`
                                : `<button type="button" class="companion-action" data-buy-companion="${escapeHtml(companion.id)}" ${Number(state.user?.coins || 0) < Number(companion.price) ? 'disabled' : ''}>🪙 ${Number(companion.price).toLocaleString('pt-BR')}</button>`;
                        return `
                            <article class="companion-card rarity-${rarityClasses[rarity]} ${isEquipped ? 'is-equipped' : ''} ${isLegendary ? 'is-legendary' : ''}">
                                <span class="companion-icon" aria-hidden="true">${isLegendary && Array.isArray(companion.icon_parts)
                                    ? companion.icon_parts.map((part) => `<span class="companion-icon-part">${escapeHtml(part)}</span>`).join('')
                                    : escapeHtml(companion.icon)}</span>
                                <span class="companion-rarity-badge">${escapeHtml(rarity)}</span>
                                <h4>${escapeHtml(companion.name)}</h4>
                                <p>${escapeHtml(companion.visual)}</p>
                                ${companion.equipment ? `<p class="companion-equipment"><strong>Equipamento lendário:</strong> ${escapeHtml(companion.equipment)}</p>` : ''}
                                ${companion.distinction ? `<p class="companion-distinction"><strong>Como reconhecer:</strong> ${escapeHtml(companion.distinction)}</p>` : ''}
                                <p class="companion-message">“${escapeHtml(companion.message)}”</p>
                                <div class="companion-card-action">${action}</div>
                            </article>
                        `;
                    }).join('')}
                </div>
            </section>
        `;
    }).join('');
}

async function refreshApp() {
    const data = await request('bootstrap');
    state.csrf = data.csrf;
    if (!data.user) {
        showAuth();
        return;
    }
    state.user = data.user;
    state.games = data.games;
    state.stats = data.stats;
    state.history = data.history;
    state.answerHistory = data.answer_history;
    state.performance = data.performance;
    state.leaderboard = data.leaderboard;
    state.achievements = data.achievements;
    state.characters = data.characters;
    state.companions = data.companions || [];
    state.pet = data.pet;
    state.unlockedDifficulties = data.unlocked_difficulties;
    enterApp();
}

async function loadQuestion() {
    window.clearTimeout(state.bridgeHideTimer);
    $('#feedback-box').hidden = true;
    $('#next-question').hidden = true;
    $('#hint-box').hidden = true;
    $('#hint-button').disabled = false;
    $('#answer-form').hidden = false;
    $('#answer-input').value = '';
    $('#question-prompt').textContent = 'Preparando sua pergunta...';
    $('#choice-list').replaceChildren();
    $('#choice-list').className = 'choice-list';
    $('#choice-list').hidden = false;
    $('#interaction-panel').replaceChildren();
    $('#interaction-panel').hidden = true;
    state.selectedBridgeAnswer = null;
    $('#answer-input').disabled = false;
    $('#answer-input').readOnly = false;
    $('#answer-form').querySelector('button').disabled = false;
    const question = await request('next_question', {});
    state.currentQuestion = question;
    $('#stage-number').textContent = question.stage;
    $('#stage-progress').style.width = `${((question.stage - 1) / question.total_stages) * 100}%`;
    $('#question-prompt').textContent = question.prompt;
    $('#hint-box').textContent = question.hint;
    const interaction = $('#interaction-panel');
    if (state.currentGame.id === 'corrida') {
        interaction.innerHTML = '<div class="race-track"><span>🏁</span><strong>Escolha uma pista e acelere!</strong><span>🏎️</span></div>';
        interaction.hidden = false;
        $('#choice-list').classList.add('race-choice-list');
        $('#choice-list').innerHTML = question.choices.map((choice, index) => `
            <button class="choice-button race-choice" type="button" data-choice="${escapeHtml(choice)}"><span class="race-lane">PISTA ${index + 1}</span><strong>${escapeHtml(choice)}</strong><span class="race-car" aria-hidden="true">🏎️</span></button>
        `).join('');
    } else if (state.currentGame.id === 'codigo') {
        interaction.innerHTML = `<div class="code-display" aria-live="polite"><span>CÓDIGO</span><strong>${escapeHtml('••')}</strong></div><div class="keypad" aria-label="Teclado numérico">${[1, 2, 3, 4, 5, 6, 7, 8, 9, '⌫', 0].map((key) => `<button class="keypad-key" type="button" data-key="${key === '⌫' ? 'delete' : key}">${key}</button>`).join('')}</div>`;
        interaction.hidden = false;
        $('#answer-input').readOnly = true;
        $('#answer-input').placeholder = 'Use o teclado para digitar o código';
        $('#choice-list').hidden = true;
    } else if (state.currentGame.id === 'ponte') {
        const sequence = Array.isArray(question.sequence) ? question.sequence : [];
        const isAdvanced = state.difficulty === 'hard';
        const bridgeParts = sequence.map((value, index) => `
            <span class="bridge-tile ${value === '?' ? 'bridge-gap' : ''}" style="--bridge-index:${index}" ${value === '?' ? 'data-bridge-gap="true"' : ''}>${escapeHtml(value)}</span>
            ${index < sequence.length - 1 ? `<span class="bridge-link" style="--bridge-index:${index}" aria-hidden="true">›</span>` : ''}
        `).join('');
        interaction.innerHTML = `
            <span class="bridge-mode-badge ${isAdvanced ? 'is-advanced' : ''}">${isAdvanced ? 'AVANÇADO · 1 tentativa · +10 moedas por acerto' : 'PONTE DOS PADRÕES'}</span>
            <div class="bridge-board" aria-label="Sequência numérica">${bridgeParts}</div>
            <p class="bridge-instruction">${isAdvanced ? 'Escolha com atenção: cada desafio permite uma tentativa.' : 'Toque na peça certa para preencher o espaço.'}</p>
            ${isAdvanced ? '<p class="bridge-advanced-note">Complete a fase para ganhar mais 20 moedas bônus.</p>' : ''}
            <button type="button" class="button button-primary button-full bridge-confirm" data-bridge-confirm disabled>Reconstruir a ponte <span>→</span></button>
        `;
        interaction.hidden = false;
        $('#choice-list').classList.add('bridge-choice-list');
        $('#choice-list').innerHTML = question.choices.map((choice) => `
            <button class="choice-button bridge-choice" type="button" data-choice="${escapeHtml(choice)}">${escapeHtml(choice)}</button>
        `).join('');
    } else {
        $('#choice-list').innerHTML = question.choices.map((choice) => `
            <button class="choice-button" type="button" data-choice="${escapeHtml(choice)}">${escapeHtml(choice)}</button>
        `).join('');
    }
}

async function startGame(gameId) {
    const game = state.games.find((item) => item.id === gameId);
    if (!game) return;
    window.clearInterval(state.gameTimer);
    state.gameTimer = null;
    state.currentGame = game;
    state.score = 0;
    state.correctCount = 0;
    state.coinsEarned = 0;
    state.finished = false;
    try {
        const result = await request('start_game', { game: gameId, difficulty: state.difficulty });
        state.pet = result.pet;
        renderPet();
        $('#mascot-roamer').hidden = true;
        $('#dashboard-view').hidden = true;
        $('#game-view').hidden = false;
        $('#game-view').classList.toggle('is-pro-mode', gameId === 'pro');
        $('#game-label').textContent = `${game.icon} ${game.title}`;
        $('#question-topic').textContent = `${game.topic} · ${(gameId === 'pro' ? 'PRO' : state.difficulty).toUpperCase()}`;
        $('#game-footer-note').textContent = gameId === 'pro'
            ? 'Desafio supremo: pense em cada etapa e confie no seu raciocínio. A recompensa cresce com seus acertos. 👑'
            : gameId === 'fracoes' && state.difficulty === 'medium'
                ? 'No nível Médio, encontre o MMC para somar e subtrair frações com denominadores diferentes. Complete a fase e ganhe +10 moedas bônus! 🧪'
                : 'Sem pressa: errar também faz parte de aprender. 💜';
        $('#game-score').textContent = '0';
        $('#game-coins').textContent = state.user.coins;
        $('#stage-progress').style.width = '0%';
        $('#game-timer').hidden = gameId !== 'pro';
        if (gameId === 'pro') {
            state.gameStartedAt = Date.now();
            updateProTimer();
            state.gameTimer = window.setInterval(updateProTimer, 1000);
        }
        await loadQuestion();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        window.clearInterval(state.gameTimer);
        state.gameTimer = null;
        state.gameStartedAt = null;
        showToast(error.message);
        if (error.message.includes('fase') || error.message.includes('sessão')) {
            await refreshApp();
        }
    }
}

async function submitAnswer(answer) {
    if (!state.currentQuestion || $('#answer-form').hidden) return;
    const submitButton = $('#answer-form').querySelector('button');
    submitButton.disabled = true;
    $('#answer-input').disabled = true;
    document.querySelectorAll('.choice-button').forEach((button) => { button.disabled = true; });
    $('#interaction-panel').querySelectorAll('button').forEach((button) => { button.disabled = true; });
    try {
        const isBridge = state.currentGame?.id === 'ponte';
        const result = await request('answer', { answer });
        playAnswerSound(result.correct);
        renderLeaderboard(result.leaderboard);
        state.score = result.score;
        state.correctCount = result.correct_count;
        state.coinsEarned += result.coins_earned;
        state.user.coins = result.coins;
        state.user.total_points = result.total_points;
        const previousActiveCharacterId = state.pet?.active_character;
        const newlyUnlocked = result.character_progress?.newly_unlocked || [];
        if (result.character_progress) {
            state.pet.unlocked_characters = result.character_progress.unlocked_characters;
            state.pet.active_character = result.character_progress.active_character;
            renderPet();
        }
        const transformedCharacter = state.characters.find((character) => character.id === result.character_progress?.active_character);
        const charactersToAnnounce = newlyUnlocked.length
            ? newlyUnlocked
            : previousActiveCharacterId !== result.character_progress?.active_character && transformedCharacter
                ? [transformedCharacter]
                : [];
        $('#game-score').textContent = result.score;
        $('#game-coins').textContent = result.coins;
        const feedback = $('#feedback-box');
        feedback.className = `feedback-box ${result.correct ? 'feedback-correct' : 'feedback-wrong'}`;
        const answerCoins = Number(result.answer_coins ?? (result.correct ? 5 : 0));
        const completionBonus = Number(result.completion_bonus ?? (result.finished ? 10 : 0));
        const isPro = state.currentGame?.id === 'pro';
        const proCorrectMessages = ['Resposta de elite! Você dominou essa etapa. 🔥', 'Precisão de campeão! Continue nesse ritmo. ✨', 'Raciocínio brilhante: mais um desafio superado! 👑'];
        const proWrongMessages = ['Desafio PRO é assim mesmo: reveja o raciocínio e siga mais forte. 💪', 'Cada erro revela uma pista. Entenda o passo e tente o próximo! 🧠', 'Você está treinando em alto nível. Use a explicação e continue! 🌟'];
        const feedbackTitle = isPro
            ? result.correct
                ? proCorrectMessages[Math.floor(Math.random() * proCorrectMessages.length)]
                : proWrongMessages[Math.floor(Math.random() * proWrongMessages.length)]
            : result.correct
                ? `Muito bem! +10 pontos e +${answerCoins} moedas ✨`
                : `Quase! A resposta era ${escapeHtml(result.expected_answer)}.`;
        feedback.innerHTML = `
            <strong>${feedbackTitle}</strong>
            <span>${escapeHtml(result.explanation)}</span>
            ${completionBonus ? `<span class="bonus-note">${isPro ? 'Recompensa PRO por desempenho' : 'Bônus de fase'}: +${completionBonus} moedas 🪙</span>` : ''}
        `;
        feedback.hidden = false;
        if (isBridge) {
            const board = $('#interaction-panel .bridge-board');
            const gap = $('#interaction-panel [data-bridge-gap]');
            if (gap) {
                gap.textContent = result.expected_answer;
                gap.classList.remove('bridge-gap');
                gap.classList.add(result.correct ? 'bridge-complete' : 'bridge-incorrect');
            }
            board?.classList.add(result.correct ? 'bridge-rebuilding' : 'bridge-failure');
            $('#choice-list').hidden = true;
            state.bridgeHideTimer = window.setTimeout(() => { $('#interaction-panel').hidden = true; }, result.correct ? 900 : 450);
        } else {
            $('#interaction-panel').hidden = true;
        }
        $('#answer-form').hidden = true;
        $('#hint-button').disabled = true;
        state.finished = result.finished;
        if (result.finished) {
            window.clearInterval(state.gameTimer);
            state.gameTimer = null;
            $('#stage-progress').style.width = '100%';
            renderAchievements(result.achievements);
            state.stats = result.stats;
            $('#stat-points').textContent = Number(state.user.total_points).toLocaleString('pt-BR');
            $('#stat-coins').textContent = Number(state.user.coins).toLocaleString('pt-BR');
            $('#stat-games').textContent = Number(state.stats.games_played).toLocaleString('pt-BR');
            $('#stat-accuracy').textContent = `${state.stats.accuracy}%`;
            $('#next-question').innerHTML = 'Ver resultado da fase <span>→</span>';
            $('#game-footer-note').textContent = isPro
                ? 'Desafio PRO concluído! Sua recompensa e seu progresso foram salvos. 🔥'
                : 'Fase completa! Suas moedas e conquistas já foram salvas. 🎉';
        } else {
            $('#next-question').innerHTML = 'Próxima pergunta <span>→</span>';
        }
        $('#next-question').hidden = false;
        charactersToAnnounce.forEach(showTransformationMessage);
        if (result.finished) await refreshApp();
    } catch (error) {
        showToast(error.message);
        submitButton.disabled = false;
        $('#answer-input').disabled = false;
        document.querySelectorAll('.choice-button').forEach((button) => { button.disabled = false; });
        $('#interaction-panel').querySelectorAll('button').forEach((button) => { button.disabled = false; });
        const confirmButton = $('#interaction-panel [data-bridge-confirm]');
        if (confirmButton && !state.selectedBridgeAnswer) confirmButton.disabled = true;
    }
}

function updateProTimer() {
    if (!state.gameStartedAt) return;
    const elapsedSeconds = Math.floor((Date.now() - state.gameStartedAt) / 1000);
    const minutes = String(Math.floor(elapsedSeconds / 60)).padStart(2, '0');
    const seconds = String(elapsedSeconds % 60).padStart(2, '0');
    $('#game-timer-value').textContent = `${minutes}:${seconds}`;
}

function showGameResults() {
    const stars = state.correctCount === 10 ? '⭐⭐⭐' : state.correctCount >= 6 ? '⭐⭐' : '⭐';
    $('#stage-number').textContent = '10';
    $('#stage-progress').style.width = '100%';
    $('#question-topic').textContent = 'FASE CONCLUÍDA';
    $('#question-prompt').textContent = `Você acertou ${state.correctCount} de 10!`;
    $('#choice-list').replaceChildren();
    $('#hint-button').hidden = true;
    $('#hint-box').hidden = true;
    $('#answer-form').hidden = true;
    $('#feedback-box').innerHTML = `
        <strong class="result-stars">${stars}</strong>
        <span>${state.currentGame?.id === 'pro' ? 'Desafio supremo concluído!' : 'Fase concluída!'} Você ganhou <strong>${state.score} pontos</strong> e <strong>${state.coinsEarned} moedas</strong>. ${state.currentGame?.id === 'pro' ? 'Sua recompensa foi calculada pelo seu desempenho. Cada etapa fortalece seu raciocínio!' : 'Cada desafio é mais uma chance de aprender!'}</span>
    `;
    $('#feedback-box').className = 'feedback-box feedback-correct result-feedback';
    $('#feedback-box').hidden = false;
    $('#next-question').hidden = false;
    $('#next-question').innerHTML = 'Escolher outra aventura <span>→</span>';
    $('#next-question').dataset.results = 'true';
    state.currentQuestion = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

$('#login-tab').addEventListener('click', () => setAuthMode(false));
$('#register-tab').addEventListener('click', () => setAuthMode(true));

$('#auth-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    setAuthMessage();
    const form = new FormData(event.currentTarget);
    const payload = {
        email: form.get('email'),
        password: form.get('password'),
    };
    if (state.registration) {
        payload.name = form.get('name');
        payload.role = form.get('role');
        payload.accessibility_enabled = form.get('accessibility_enabled') === 'true';
    }
    $('#auth-submit').disabled = true;
    try {
        await request(state.registration ? 'register' : 'login', payload);
        await refreshApp();
        event.currentTarget.reset();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        setAuthMessage(error.message);
    } finally {
        $('#auth-submit').disabled = false;
    }
});

$('#accessibility-toggle').addEventListener('click', openAccessibilityDialog);
$('#accessibility-cancel').addEventListener('click', closeAccessibilityDialog);
$('#accessibility-dialog').addEventListener('close', () => {
    state.accessibilityDialogReturnFocus?.focus();
    state.accessibilityDialogReturnFocus = null;
});
$('#accessibility-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const saveButton = $('#accessibility-save');
    const message = $('#accessibility-message');
    message.hidden = true;
    saveButton.disabled = true;
    try {
        const result = await request('save_accessibility', {
            accessibility_enabled: form.get('high_contrast') === 'on',
            font_size: form.get('font_size'),
        });
        state.user = { ...state.user, ...result.preferences };
        applyAccessibilitySettings(state.user);
        closeAccessibilityDialog();
        showToast(result.message);
    } catch (error) {
        message.textContent = error.message;
        message.hidden = false;
    } finally {
        saveButton.disabled = false;
    }
});

$('#logout-button').addEventListener('click', async () => {
    try {
        await request('logout', {});
        await refreshApp();
    } catch (error) {
        showToast(error.message);
    }
});

$('#pet-rename-button').addEventListener('click', () => openPetNameDialog());

$('#pet-name-cancel').addEventListener('click', () => {
    if (state.pet?.name) $('#pet-name-dialog').close();
});

$('#pet-name-dialog').addEventListener('cancel', (event) => {
    if (!state.pet?.name) event.preventDefault();
});

$('#pet-name-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = event.currentTarget.querySelector('[type="submit"]');
    const name = $('#pet-name-input').value.trim();
    $('#pet-name-error').hidden = true;
    submitButton.disabled = true;
    try {
        const result = await request('set_pet_name', { name });
        state.pet = result.pet;
        renderPet();
        $('#pet-name-dialog').close();
        showToast(result.message);
    } catch (error) {
        $('#pet-name-error').textContent = error.message;
        $('#pet-name-error').hidden = false;
    } finally {
        submitButton.disabled = false;
    }
});

$('#pet-affection-button').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    button.classList.remove('is-happy');
    void button.offsetWidth;
    button.classList.add('is-happy');
    try {
        const result = await request('pet_affection', {});
        state.pet = result.pet;
        renderPet();
        showToast(result.message);
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
        window.setTimeout(() => button.classList.remove('is-happy'), 1200);
    }
});

function addMascotHearts(button) {
    ['♥', '♥', '✦', '♥'].forEach((symbol) => {
        const heart = document.createElement('span');
        heart.className = 'mascot-heart';
        heart.setAttribute('aria-hidden', 'true');
        heart.textContent = symbol;
        heart.addEventListener('animationend', () => heart.remove(), { once: true });
        button.append(heart);
    });
}

function reactToMascot(withHearts = true) {
    const button = $('#mascot-bunny-button');
    const track = $('.mascot-track');
    if (track.classList.contains('is-reacting')) return;
    track.classList.add('is-reacting');
    button.classList.add('is-excited');
    if (withHearts) addMascotHearts(button);
    window.clearTimeout(track.reactionTimer);
    track.reactionTimer = window.setTimeout(() => {
        track.classList.remove('is-reacting');
        button.classList.remove('is-excited');
    }, 1250);
}

$('#mascot-bunny-button').addEventListener('pointerenter', () => reactToMascot());
$('#mascot-bunny-button').addEventListener('focus', () => reactToMascot());
$('#mascot-bunny-button').addEventListener('click', () => reactToMascot());

$('#music-toggle').addEventListener('click', async () => {
    if (state.musicEnabled && state.audioContext?.state === 'running') {
        state.musicEnabled = false;
        window.clearInterval(state.musicTimer);
        state.musicTimer = null;
        await state.audioContext.suspend();
        localStorage.setItem('mathplayMusic', 'false');
        updateMusicButton();
        return;
    }
    state.musicEnabled = true;
    localStorage.setItem('mathplayMusic', 'true');
    await startMusic();
});

document.addEventListener('pointerdown', (event) => {
    if (event.target instanceof Element && event.target.closest('#music-toggle')) return;
    if (state.musicEnabled) {
        startMusic().catch(() => {
            showToast('Não foi possível iniciar a música. Você ainda pode curtir as atividades sem som.');
        });
    }
}, { once: true });

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    if (event.target instanceof Element && event.target.closest('#music-toggle')) return;
    if (state.musicEnabled) {
        startMusic().catch(() => {
            showToast('Não foi possível iniciar a música. Você ainda pode curtir as atividades sem som.');
        });
    }
}, { once: true });

document.querySelectorAll('.difficulty-button').forEach((button) => {
    button.addEventListener('click', () => {
        if (!state.unlockedDifficulties.includes(button.dataset.difficulty)) return;
        state.difficulty = button.dataset.difficulty;
        renderDifficulties();
    });
});

$('#game-grid').addEventListener('click', (event) => {
    const button = event.target.closest('[data-play]');
    if (button) startGame(button.dataset.play);
});

$('#hint-button').addEventListener('click', () => {
    $('#hint-box').hidden = false;
    $('#hint-button').textContent = '💡 Dica revelada';
});

$('#choice-list').addEventListener('click', (event) => {
    const button = event.target.closest('[data-choice]');
    if (button && !button.disabled) {
        if (state.currentGame?.id === 'ponte') {
            state.selectedBridgeAnswer = button.dataset.choice;
            $('#answer-input').value = state.selectedBridgeAnswer;
            $('#choice-list').querySelectorAll('[data-choice]').forEach((choiceButton) => {
                choiceButton.classList.toggle('is-selected', choiceButton === button);
            });
            $('#interaction-panel [data-bridge-gap]').textContent = state.selectedBridgeAnswer;
            $('#interaction-panel [data-bridge-confirm]').disabled = false;
            return;
        }
        $('#answer-input').value = button.dataset.choice;
        submitAnswer(button.dataset.choice);
    }
});

$('#interaction-panel').addEventListener('click', (event) => {
    const key = event.target.closest('[data-key]');
    if (key && !key.disabled) {
        const input = $('#answer-input');
        input.value = key.dataset.key === 'delete' ? input.value.slice(0, -1) : `${input.value}${key.dataset.key}`.slice(0, 3);
        return;
    }
    const confirm = event.target.closest('[data-bridge-confirm]');
    if (confirm && !confirm.disabled && state.selectedBridgeAnswer) {
        submitAnswer(state.selectedBridgeAnswer);
    }
});

$('#answer-form').addEventListener('submit', (event) => {
    event.preventDefault();
    const value = $('#answer-input').value.trim();
    if (value) submitAnswer(value);
});

$('#next-question').addEventListener('click', async (event) => {
    if (event.currentTarget.dataset.results === 'true') {
        event.currentTarget.dataset.results = 'false';
        $('#hint-button').hidden = false;
        $('#interaction-panel').hidden = true;
        $('#choice-list').hidden = false;
        $('#answer-input').readOnly = false;
        $('#game-view').hidden = true;
        $('#dashboard-view').hidden = false;
        $('#mascot-roamer').hidden = false;
        $('#game-view').classList.remove('is-pro-mode');
        $('#game-timer').hidden = true;
        window.clearInterval(state.gameTimer);
        state.gameTimer = null;
        state.gameStartedAt = null;
        state.currentGame = null;
        await refreshApp();
        $('#trilha').scrollIntoView({ behavior: 'smooth' });
        return;
    }
    if (state.finished) {
        showGameResults();
        return;
    }
    try {
        await loadQuestion();
    } catch (error) {
        showToast(error.message);
    }
});

$('#back-to-hub').addEventListener('click', async () => {
    window.clearInterval(state.gameTimer);
    state.gameTimer = null;
    state.gameStartedAt = null;
    $('#game-view').hidden = true;
    $('#game-view').classList.remove('is-pro-mode');
    $('#game-timer').hidden = true;
    $('#dashboard-view').hidden = false;
    $('#mascot-roamer').hidden = false;
    $('#hint-button').hidden = false;
    await refreshApp();
    $('#trilha').scrollIntoView({ behavior: 'smooth' });
});

$('#character-grid').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-select-character]');
    if (!button || button.disabled) return;
    const characterId = button.dataset.selectCharacter;
    button.disabled = true;
    try {
        const result = await request('set_active_character', { character_id: characterId });
        state.pet = result.pet;
        renderPet();
        showToast(result.message);
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
    }
});

$('#companion-catalog').addEventListener('click', async (event) => {
    const buyButton = event.target.closest('[data-buy-companion]');
    const equipButton = event.target.closest('[data-equip-companion]');
    const button = buyButton || equipButton;
    if (!button || button.disabled) return;
    button.disabled = true;
    try {
        const result = await request(buyButton ? 'buy_companion' : 'equip_companion', {
            companion_id: buyButton ? buyButton.dataset.buyCompanion : equipButton.dataset.equipCompanion,
        });
        state.pet = result.pet;
        if (Number.isFinite(Number(result.coins))) {
            state.user.coins = Number(result.coins);
            $('#stat-coins').textContent = Number(result.coins).toLocaleString('pt-BR');
        }
        renderPet();
        if (buyButton) {
            const companion = state.companions.find((item) => item.id === buyButton.dataset.buyCompanion);
            if (companion) {
                showCompanionUnlock(companion);
            } else {
                showToast(result.message);
            }
        } else {
            showToast(result.message);
        }
    } catch (error) {
        showToast(error.message);
        button.disabled = false;
    }
});

$('#celebration-continue').addEventListener('click', () => {
    const dialog = $('#transformation-dialog');
    if (typeof dialog.close === 'function') {
        dialog.close();
        return;
    }
    dialog.removeAttribute('open');
    dialog.classList.remove('is-open');
    dialog.removeAttribute('aria-modal');
    window.setTimeout(showNextCelebration, 180);
});
$('#transformation-dialog').addEventListener('close', () => {
    $('#celebration-particles').replaceChildren();
    window.setTimeout(showNextCelebration, 180);
});

request('bootstrap')
    .then((data) => {
        state.csrf = data.csrf;
        if (!data.user) {
            showAuth();
            return;
        }
        state.user = data.user;
        state.games = data.games;
        state.stats = data.stats;
        state.history = data.history;
        state.answerHistory = data.answer_history;
        state.performance = data.performance;
        state.leaderboard = data.leaderboard;
        state.achievements = data.achievements;
        state.characters = data.characters;
        state.companions = data.companions || [];
        state.pet = data.pet;
        state.unlockedDifficulties = data.unlocked_difficulties;
        enterApp();
    })
    .catch((error) => {
        setAuthMessage(error.message);
    });

updateMusicButton();

window.setInterval(async () => {
    if (!state.user) return;
    try {
        const result = await request('pet_state');
        state.pet = result.pet;
        renderPet();
        if (!state.pet.name && !$('#pet-name-dialog').open) openPetNameDialog(true);
    } catch (error) {
        showToast(error.message);
    }
}, 60_000);
