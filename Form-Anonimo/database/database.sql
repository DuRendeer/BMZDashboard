USE u406174804_teste;

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    input_type ENUM('text', 'textarea', 'select', 'radio', 'checkbox') DEFAULT 'text',
    options JSON DEFAULT NULL,
    required BOOLEAN DEFAULT TRUE,
    has_image BOOLEAN DEFAULT FALSE,
    image_path VARCHAR(255) DEFAULT NULL,
    position_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    question_id INT,
    response_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

INSERT INTO questions (title, question_text, input_type, required, position_order) VALUES
('Sua Mensagem', 'Digite sua mensagem aqui...', 'textarea', TRUE, 1);