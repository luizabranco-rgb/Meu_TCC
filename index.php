<?php
header('Cache-Control: no-cache, must-revalidate');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#11132c">
    <meta name="description" content="Aprenda Matemática jogando, ganhando moedas e evoluindo com a MathRabbit!">
    <title>MathRabbit — aprender pode ser uma aventura</title>
    <link rel="icon" type="image/svg+xml" href="mathrabbit-logo.svg?v=20261009-19">
    <link rel="stylesheet" href="styles.css?v=20261023-1">
    <script src="app.js?v=20261023-3" defer></script>
</head>
<body>
    <div class="ambient ambient-one" aria-hidden="true"></div>
    <div class="ambient ambient-two" aria-hidden="true"></div>
    <div class="sparkle-field" aria-hidden="true"></div>

    <header class="topbar">
        <a class="brand" href="#" aria-label="MathRabbit, início">
            <span class="brand-mark"><img src="mathrabbit-logo.svg" alt=""></span>
            <span>Math<span class="brand-accent">Rabbit</span></span>
        </a>
        <nav id="main-nav" class="main-nav" aria-label="Navegação principal" hidden>
            <a href="#trilha">Minha trilha</a>
            <a href="#desempenho">Meu Desempenho</a>
            <a href="#ranking">Ranking</a>
            <a href="#personagem">Meu personagem</a>
            <button class="button button-quiet button-small accessibility-toggle" id="accessibility-toggle" type="button" aria-haspopup="dialog" aria-label="Configurações de acessibilidade">Acessibilidade</button>
            <button class="button button-quiet button-small" id="logout-button" type="button">Sair</button>
        </nav>
        <button class="music-toggle" id="music-toggle" type="button" aria-pressed="false" aria-label="Ativar música ambiente">
            <span class="music-icon" aria-hidden="true">♫</span>
            <span class="music-label">Ativar música</span>
            <span class="music-indicator" aria-hidden="true"></span>
        </button>
    </header>

    <main>
        <section id="auth-screen" class="auth-layout">
            <div class="welcome-copy">
                <div class="eyebrow"><span class="eyebrow-dot"></span> SUA AVENTURA COMEÇA AQUI</div>
                <h1>Matemática que<br>vira <span class="gradient-text">diversão.</span></h1>
                <p>Resolva desafios, explore novos mundos e transforme cada acerto em uma conquista.</p>
                <div class="welcome-pills">
                    <span>🎮 8 jogos</span>
                    <span>✨ Personagem evolutivo</span>
                    <span>✨ No seu ritmo</span>
                </div>
                <div class="welcome-mascot" aria-hidden="true">
                    <span class="mascot-orbit orbit-one">＋</span>
                    <span class="mascot-orbit orbit-two">÷</span>
                    <span class="mascot-face">🐰</span>
                    <span class="mascot-caption">Oi! Vamos aprender? <span>✦</span></span>
                </div>
            </div>

            <div class="auth-card">
                <div class="auth-card-heading">
                    <span class="mini-sparkle">✦</span>
                    <h2 id="auth-title">Que bom ter você!</h2>
                    <p id="auth-subtitle">Entre para continuar sua aventura.</p>
                </div>
                <div class="auth-tabs" role="tablist" aria-label="Acesso à conta">
                    <button class="auth-tab is-active" id="login-tab" type="button" role="tab" aria-selected="true">Entrar</button>
                    <button class="auth-tab" id="register-tab" type="button" role="tab" aria-selected="false">Criar conta</button>
                </div>
                <form id="auth-form" novalidate>
                    <label class="field" id="name-field" hidden>
                        <span>Como podemos te chamar?</span>
                        <input name="name" type="text" autocomplete="name" maxlength="60" placeholder="Seu nome" required>
                    </label>
                    <label class="field">
                        <span>Seu e-mail</span>
                        <input name="email" type="email" autocomplete="email" maxlength="190" placeholder="voce@email.com" required>
                    </label>
                    <label class="field">
                        <span>Senha</span>
                        <input name="password" type="password" autocomplete="current-password" minlength="8" maxlength="72" placeholder="Pelo menos 8 caracteres" required>
                    </label>
                    <label class="field" id="role-field" hidden>
                        <span>Quero entrar como</span>
                        <select name="role">
                            <option value="student">Estudante</option>
                            <option value="teacher">Professor(a)</option>
                        </select>
                    </label>
                    <label class="accessibility-signup" id="accessibility-signup-field" hidden>
                        <input name="accessibility_enabled" type="checkbox" value="true">
                        <span>Preciso de acessibilidade: alto contraste e texto ajustável.</span>
                    </label>
                    <div class="form-message" id="auth-message" role="alert" hidden></div>
                    <button class="button button-primary button-full" id="auth-submit" type="submit">Entrar na aventura <span>→</span></button>
                </form>
                <p class="auth-footnote">Feito para aprender, explorar e se divertir. 💜</p>
            </div>
        </section>

        <section id="app-screen" hidden>
            <section class="dashboard page-section" id="dashboard-view">
                <div class="dashboard-hero">
                    <div class="hero-copy">
                        <div class="eyebrow"><span class="eyebrow-dot"></span> SUA PRÓXIMA MISSÃO ESPERA</div>
                        <h1>Olá, <span id="student-name">aventureiro</span>! <span class="wave">👋</span></h1>
                        <p>Um pouquinho de Matemática hoje pode virar uma grande conquista amanhã.</p>
                        <a class="button button-primary" href="#trilha">Explorar minha trilha <span>↓</span></a>
                    </div>
                    <div class="hero-art">
                        <div class="hero-glow"></div>
                        <div class="hero-speech">Você consegue! <span>💫</span></div>
                        <div class="hero-bunny" id="hero-active-character" aria-hidden="true">🐰</div>
                        <span class="hero-star star-a">✦</span>
                        <span class="hero-star star-b">✧</span>
                        <span class="hero-plus">＋</span>
                        <div class="hero-transform-particles" id="hero-transform-particles" aria-hidden="true" hidden></div>
                    </div>
                </div>

                <div class="stat-grid" aria-label="Suas estatísticas">
                    <article class="stat-card">
                        <span class="stat-icon stat-purple">🏆</span>
                        <div><span class="stat-label">Pontos</span><strong id="stat-points">0</strong></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-yellow">🪙</span>
                        <div><span class="stat-label">Moedas</span><strong id="stat-coins">0</strong></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-blue">🎮</span>
                        <div><span class="stat-label">Partidas</span><strong id="stat-games">0</strong></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-pink">🎯</span>
                        <div><span class="stat-label">Precisão</span><strong id="stat-accuracy">0%</strong></div>
                    </article>
                </div>

                <section id="trilha" class="content-section">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">ESCOLHA SEU DESAFIO</div>
                            <h2>Sua trilha de aventuras <span>🗺️</span></h2>
                        </div>
                        <div class="difficulty-picker" aria-label="Nível de dificuldade">
                            <button class="difficulty-button is-selected" type="button" data-difficulty="easy">Fácil</button>
                            <button class="difficulty-button" type="button" data-difficulty="medium">Médio <span class="lock-mark">🔒</span></button>
                            <button class="difficulty-button" type="button" data-difficulty="hard">Difícil / Avançado <span class="lock-mark">🔒</span></button>
                        </div>
                    </div>
                    <p class="section-note" id="difficulty-note"><span>Seu nível atual: <strong id="current-level">Fácil</strong></span> · Cada fase tem 10 desafios. Acerte para ganhar pontos e moedas!</p>
                    <div id="game-grid" class="game-grid"></div>
                </section>

                <div class="lower-grid">
                    <section class="content-section performance-section" id="desempenho">
                        <div class="section-heading compact-heading">
                            <div>
                                <div class="eyebrow">UM GUIA FEITO PARA VOCÊ</div>
                                <h2>Meu Desempenho <span>🎯</span></h2>
                            </div>
                        </div>
                        <p class="performance-intro">Veja onde você está mandando bem e o que vale a pena estudar um pouquinho mais.</p>
                        <div id="performance-summary" class="performance-summary"></div>
                        <div id="performance-highlights" class="performance-highlights"></div>
                        <section class="study-now-panel" aria-labelledby="study-now-title">
                            <div class="study-now-icon" aria-hidden="true">🧭</div>
                            <div>
                                <span class="study-now-eyebrow">SEU PRÓXIMO PASSO</span>
                                <h3 id="study-now-title">O que estudar agora?</h3>
                                <div id="study-now-content"></div>
                            </div>
                        </section>
                        <div class="performance-topic-heading performance-chart-heading">
                            <div>
                                <h3>Seu mapa de aprendizagem</h3>
                                <p>Acertos e erros em cada assunto. A maestria cresce com a prática!</p>
                            </div>
                            <div class="performance-legend"><span><i class="legend-correct"></i>Acertos</span><span><i class="legend-incorrect"></i>Para praticar</span></div>
                        </div>
                        <div id="performance-list" class="performance-list"></div>
                        <div class="performance-mistakes-heading">
                            <div>
                                <h3>Aprendendo com as respostas</h3>
                                <p>Veja a questão, sua resposta e o raciocínio esperado.</p>
                            </div>
                        </div>
                        <div id="performance-mistakes" class="performance-mistakes"></div>
                    </section>
                    <section class="content-section achievements-section">
                        <div class="section-heading compact-heading">
                            <div>
                                <div class="eyebrow">SUAS CONQUISTAS</div>
                                <h2>Medalhas <span>🏅</span></h2>
                            </div>
                        </div>
                        <div class="achievement-list" id="achievement-list"></div>
                    </section>
                    <section class="content-section history-section">
                        <div class="section-heading compact-heading">
                            <div>
                                <div class="eyebrow">SEU CAMINHO</div>
                                <h2>Partidas recentes <span>📚</span></h2>
                            </div>
                        </div>
                        <div class="history-list" id="history-list"></div>
                    </section>
                    <section class="content-section answer-history-section">
                        <div class="section-heading compact-heading">
                            <div>
                                <div class="eyebrow">SEUS ÚLTIMOS DESAFIOS</div>
                                <h2>Respostas recentes <span>📝</span></h2>
                            </div>
                        </div>
                        <div class="answer-history-list" id="answer-history-list"></div>
                    </section>
                    <section class="content-section leaderboard-section" id="ranking">
                        <div class="section-heading compact-heading">
                            <div>
                                <div class="eyebrow">VOCÊ TAMBÉM PODE CHEGAR LÁ</div>
                                <h2>Ranking de estudantes <span>🏆</span></h2>
                            </div>
                            <span class="ranking-update">Atualizado com pontos salvos</span>
                        </div>
                        <div id="leaderboard-podium" class="leaderboard-podium"></div>
                        <div id="leaderboard-list" class="leaderboard-list"></div>
                    </section>
                </div>
            </section>

            <section class="game-view page-section" id="game-view" hidden>
                <button type="button" class="back-button" id="back-to-hub">← Voltar à trilha</button>
                <div class="game-shell">
                    <div class="game-topline">
                        <span id="game-label" class="game-label"></span>
                        <div class="game-topline-meta">
                            <span class="game-timer" id="game-timer" hidden aria-label="Tempo decorrido no Modo PRO">⏱ <strong id="game-timer-value">00:00</strong></span>
                            <span class="game-stage-label">FASE <strong id="stage-number">1</strong> / 10</span>
                        </div>
                    </div>
                    <div class="progress-track" aria-label="Progresso da fase"><div id="stage-progress" class="progress-fill"></div></div>
                    <div class="game-scoreline"><span>✨ <strong id="game-score">0</strong> pontos</span><span>🪙 <strong id="game-coins">0</strong></span></div>
                    <div class="question-card" id="question-card">
                        <div class="question-mascot">🐰</div>
                        <div class="eyebrow" id="question-topic">DESAFIO MATEMÁTICO</div>
                        <h1 id="question-prompt">Preparando sua pergunta...</h1>
                        <button class="hint-button" type="button" id="hint-button">💡 Preciso de uma dica</button>
                        <div id="hint-box" class="hint-box" hidden></div>
                        <div id="interaction-panel" class="interaction-panel" hidden></div>
                        <div id="choice-list" class="choice-list"></div>
                        <form id="answer-form" class="answer-form">
                            <label for="answer-input" class="visually-hidden">Sua resposta</label>
                            <input id="answer-input" name="answer" inputmode="decimal" autocomplete="off" placeholder="Ou digite sua resposta..." maxlength="30" required>
                            <button class="button button-primary" type="submit">Responder <span>→</span></button>
                        </form>
                        <div id="feedback-box" class="feedback-box" role="status" hidden></div>
                        <button class="button button-primary button-full next-question-button" id="next-question" type="button" hidden>Próxima pergunta <span>→</span></button>
                    </div>
                    <div class="game-footer-note" id="game-footer-note">Sem pressa: errar também faz parte de aprender. 💜</div>
                </div>
            </section>

            <section id="personagem" class="shop-section page-section">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">EVOLUA COM SUAS CONQUISTAS</div>
                        <h2>Transformações <span>✨</span></h2>
                    </div>
                    <div class="character-points-pill">✨ <strong id="character-points">0</strong> pontos</div>
                </div>
                <div class="shop-layout">
                    <div class="avatar-stage">
                        <div class="avatar-stars">✦ <span>✧</span> ✦</div>
                        <div class="avatar-character" id="avatar-character" role="img" aria-label="Seu personagem coelho">
                            <div class="avatar-creature">
                                <span class="avatar-transformed-icon" id="avatar-transformed-icon" aria-hidden="true" hidden></span>
                                <div class="avatar-layer avatar-layer-base" id="avatar-rabbit-art" aria-hidden="true">
                                    <svg viewBox="0 0 200 225" xmlns="http://www.w3.org/2000/svg">
                                        <ellipse class="bunny-ground-shadow" cx="100" cy="212" rx="53" ry="8"/>
                                        <ellipse class="bunny-ear" cx="78" cy="40" rx="19" ry="41" transform="rotate(-9 78 40)"/>
                                        <ellipse class="bunny-ear-inner" cx="78" cy="40" rx="9" ry="29" transform="rotate(-9 78 40)"/>
                                        <ellipse class="bunny-ear" cx="123" cy="38" rx="19" ry="42" transform="rotate(8 123 38)"/>
                                        <ellipse class="bunny-ear-inner" cx="123" cy="38" rx="9" ry="30" transform="rotate(8 123 38)"/>
                                        <ellipse class="bunny-foot" cx="78" cy="195" rx="27" ry="17" transform="rotate(-9 78 195)"/>
                                        <ellipse class="bunny-foot" cx="122" cy="195" rx="27" ry="17" transform="rotate(9 122 195)"/>
                                        <path class="bunny-arm-shape" d="M68 123C54 122 46 135 47 153c1 12 8 18 17 15l12-10-1-25z"/>
                                        <path class="bunny-arm-shape" d="M132 123c14-1 22 12 21 30-1 12-8 18-17 15l-12-10 1-25z"/>
                                        <ellipse class="bunny-body-shape" cx="100" cy="155" rx="43" ry="49"/>
                                        <ellipse class="bunny-belly-shape" cx="100" cy="165" rx="25" ry="30"/>
                                        <ellipse class="bunny-head-shape" cx="100" cy="91" rx="51" ry="45"/>
                                        <ellipse class="bunny-cheek" cx="68" cy="99" rx="8" ry="5"/>
                                        <ellipse class="bunny-cheek" cx="132" cy="99" rx="8" ry="5"/>
                                        <ellipse class="bunny-eye" cx="84" cy="88" rx="3.5" ry="5"/>
                                        <ellipse class="bunny-eye" cx="116" cy="88" rx="3.5" ry="5"/>
                                        <path class="bunny-nose" d="M96 99q4-4 8 0-4 6-8 0"/>
                                        <path class="bunny-mouth" d="M100 105v3m0 0q-4 4-7 0m7 0q4 4 7 0"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="character-transform-particles" id="character-transform-particles" aria-hidden="true" hidden></div>
                        <div class="equipped-companion" id="equipped-companion" aria-label="Nenhum companion equipado" hidden>
                            <span id="equipped-companion-icon" aria-hidden="true"></span>
                            <strong id="equipped-companion-name"></strong>
                            <small id="equipped-companion-gear" hidden></small>
                        </div>
                        <p id="avatar-character-caption">Seu personagem evolui com seus pontos!</p>
                        <div class="pet-card">
                            <div class="pet-name-row">
                                <div>
                                    <span class="pet-label">SEU PERSONAGEM</span>
                                    <strong id="pet-name-display">Sem nome</strong>
                                </div>
                                <button class="pet-rename-button" id="pet-rename-button" type="button">Editar nome</button>
                            </div>
                            <div class="pet-energy-row">
                                <span>Energia</span>
                                <strong id="pet-energy-label">100%</strong>
                            </div>
                            <div class="pet-energy-track" role="progressbar" aria-label="Energia do personagem" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100">
                                <span id="pet-energy-fill"></span>
                            </div>
                            <div class="pet-action-row">
                                <button class="pet-affection-button" id="pet-affection-button" type="button">❤️ Fazer carinho</button>
                                <span id="pet-affection-count">0 carinhos</span>
                            </div>
                        </div>
                    </div>
                    <div class="character-progress-wrap">
                        <p class="shop-caption">Ganhe pontos jogando para desbloquear novas formas. Ao atingir cada marco, seu personagem se transforma automaticamente. Você pode escolher qualquer forma já desbloqueada.</p>
                        <div id="character-grid" class="character-grid" aria-label="Transformações do personagem"></div>
                    </div>
                </div>
                <section class="companion-shop" aria-labelledby="companion-shop-title">
                    <div class="companion-shop-heading">
                        <div>
                            <div class="eyebrow">UM COMPANHEIRO PARA CADA AVENTURA</div>
                            <h2 id="companion-shop-title">Loja de companions <span>🐾</span></h2>
                        </div>
                        <div class="coin-pill">🪙 <strong id="companion-coins">0</strong> moedas</div>
                    </div>
                    <p class="shop-caption">Escolha entre 36 bichinhos fofos, cada um com visual próprio. Compre e equipe um para ficar ao lado da sua forma atual; os lendários vêm com acessórios exclusivos e brilho especial. Transformações e companions são coleções independentes.</p>
                    <div id="companion-catalog" class="companion-catalog" aria-live="polite"></div>
                </section>
            </section>
        </section>
    </main>
    <dialog id="transformation-dialog" class="transformation-dialog" aria-labelledby="transformation-title" aria-describedby="transformation-message">
        <div class="celebration-card">
            <div class="celebration-glow" aria-hidden="true"></div>
            <div id="celebration-particles" class="celebration-particles" aria-hidden="true"></div>
            <span class="celebration-eyebrow">✦ UMA CONQUISTA ESPECIAL ✦</span>
            <div class="celebration-character" aria-hidden="true">
                <span id="transformation-character-icon"></span>
            </div>
            <h2 id="transformation-title">Transformação desbloqueada!</h2>
            <strong id="transformation-character-name" class="celebration-character-name"></strong>
            <p id="transformation-message"></p>
            <button class="button button-primary celebration-continue" id="celebration-continue" type="button">Oba! Continuar <span>✨</span></button>
        </div>
    </dialog>
    <dialog id="pet-name-dialog" class="pet-name-dialog" aria-labelledby="pet-name-title">
        <form id="pet-name-form">
            <span class="pet-label">SEU PERSONAGEM</span>
            <h2 id="pet-name-title">Como seu personagem vai se chamar?</h2>
            <p>Escolha um nome especial para acompanhar todas as suas transformações.</p>
            <label for="pet-name-input">Nome do personagem</label>
            <input id="pet-name-input" name="pet_name" maxlength="24" autocomplete="off" required>
            <div class="pet-name-actions">
                <button class="button button-quiet" id="pet-name-cancel" type="button">Agora não</button>
                <button class="button button-primary" type="submit">Salvar nome <span>→</span></button>
            </div>
            <p class="pet-name-error" id="pet-name-error" role="alert" hidden></p>
        </form>
    </dialog>
    <dialog id="accessibility-dialog" class="accessibility-dialog" aria-labelledby="accessibility-title">
        <form id="accessibility-form">
            <span class="pet-label">PERSONALIZE SUA EXPERIÊNCIA</span>
            <h2 id="accessibility-title">Acessibilidade</h2>
            <p>Escolha o contraste e o tamanho do texto que deixam o app mais confortável para você.</p>
            <label class="accessibility-contrast">
                <input id="accessibility-contrast-input" name="high_contrast" type="checkbox">
                <span>Ativar alto contraste (texto branco sobre fundo preto)</span>
            </label>
            <fieldset class="accessibility-font-options">
                <legend>Tamanho do texto</legend>
                <label><input type="radio" name="font_size" value="small" required> Menor</label>
                <label><input type="radio" name="font_size" value="normal"> Padrão</label>
                <label><input type="radio" name="font_size" value="large"> Maior</label>
            </fieldset>
            <p id="accessibility-message" class="accessibility-message" role="alert" hidden></p>
            <div class="pet-name-actions">
                <button class="button button-quiet" id="accessibility-cancel" type="button">Cancelar</button>
                <button class="button button-primary" id="accessibility-save" type="submit">Salvar preferências</button>
            </div>
        </form>
    </dialog>
    <div id="mascot-roamer" class="mascot-roamer">
        <div class="mascot-track">
            <button class="mascot-bunny-button" id="mascot-bunny-button" type="button" aria-label="Clique no coelhinho para ele pular e soltar corações">
                <span class="mascot-facing">
                <svg class="mascot-bunny" viewBox="0 0 96 112" role="img" aria-label="Coelhinho bebê">
                    <ellipse class="mascot-shadow" cx="48" cy="105" rx="28" ry="5"/>
                    <ellipse class="mascot-ear" cx="34" cy="20" rx="10" ry="20" transform="rotate(-12 34 20)"/>
                    <ellipse class="mascot-ear-inner" cx="34" cy="20" rx="4" ry="13" transform="rotate(-12 34 20)"/>
                    <ellipse class="mascot-ear" cx="62" cy="19" rx="10" ry="21" transform="rotate(10 62 19)"/>
                    <ellipse class="mascot-ear-inner" cx="62" cy="19" rx="4" ry="14" transform="rotate(10 62 19)"/>
                    <ellipse class="mascot-paw" cx="34" cy="98" rx="11" ry="6"/>
                    <ellipse class="mascot-paw" cx="62" cy="98" rx="11" ry="6"/>
                    <ellipse class="mascot-arm" cx="29" cy="77" rx="8" ry="5" transform="rotate(-28 29 77)"/>
                    <ellipse class="mascot-arm" cx="67" cy="77" rx="8" ry="5" transform="rotate(28 67 77)"/>
                    <ellipse class="mascot-body" cx="48" cy="81" rx="23" ry="23"/>
                    <ellipse class="mascot-belly" cx="48" cy="85" rx="13" ry="15"/>
                    <ellipse class="mascot-head" cx="48" cy="49" rx="33" ry="31"/>
                    <ellipse class="mascot-cheek" cx="29" cy="52" rx="6" ry="4"/>
                    <ellipse class="mascot-cheek" cx="67" cy="52" rx="6" ry="4"/>
                    <ellipse class="mascot-eye" cx="38" cy="44" rx="2.3" ry="3.5"/>
                    <ellipse class="mascot-eye" cx="58" cy="44" rx="2.3" ry="3.5"/>
                    <path class="mascot-nose" d="M45 50q3-3 6 0-3 5-6 0"/>
                    <path class="mascot-mouth" d="M48 54v2m0 0q-3 3-5 0m5 0q3 3 5 0"/>
                </svg>
                </span>
                <span class="mascot-wave" aria-hidden="true">✦</span>
            </button>
        </div>
    </div>
    <div id="toast" class="toast" role="status" aria-live="polite"></div>
</body>
</html>
