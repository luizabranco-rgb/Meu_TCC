CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL,
    accessibility_enabled TINYINT(1) NOT NULL DEFAULT 0,
    font_size ENUM('small', 'normal', 'large') NOT NULL DEFAULT 'normal',
    coins INT NOT NULL DEFAULT 30,
    total_points INT NOT NULL DEFAULT 0,
    pet_name VARCHAR(255) NOT NULL DEFAULT '',
    energy TINYINT UNSIGNED NOT NULL DEFAULT 100,
    last_decay_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unlocked_characters VARCHAR(1000) NOT NULL DEFAULT '["rabbit"]',
    active_character VARCHAR(100) NOT NULL DEFAULT 'rabbit',
    affection_count INT UNSIGNED NOT NULL DEFAULT 0,
    owned_companions VARCHAR(5000) NOT NULL DEFAULT '[]',
    equipped_companion_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_energy CHECK (energy BETWEEN 0 AND 100),
    CONSTRAINT chk_users_accessibility CHECK (accessibility_enabled IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    game_key VARCHAR(100) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    score INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    status ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_game_runs_user_id (user_id),
    CONSTRAINT fk_game_runs_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    expected_answer TEXT NOT NULL,
    submitted_answer TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    explanation TEXT NOT NULL,
    answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_answers_run_id (run_id),
    CONSTRAINT fk_answers_run
        FOREIGN KEY (run_id) REFERENCES game_runs (id) ON DELETE CASCADE,
    CONSTRAINT chk_answers_is_correct CHECK (is_correct IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achievements (
    id VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(32) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_achievements (
    user_id INT UNSIGNED NOT NULL,
    achievement_id VARCHAR(100) NOT NULL,
    unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    CONSTRAINT fk_user_achievements_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_achievements_achievement
        FOREIGN KEY (achievement_id) REFERENCES achievements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO achievements (id, title, description, icon) VALUES
    ('first_run', 'Primeira missão', 'Conclua sua primeira partida.', '🚀'),
    ('perfect_run', 'Mente brilhante', 'Acerte todas as perguntas de uma fase.', '💎'),
    ('ten_correct', 'Craque dos números', 'Acerte 10 perguntas no total.', '⭐')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    icon = VALUES(icon);
