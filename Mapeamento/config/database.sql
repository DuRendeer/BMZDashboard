-- Banco de dados para Sistema de Mapeamento de Salas
-- Nome do banco: u406174804_BANCO_BMZ
-- Usuário: u406174804_TI
-- Servidor: 127.0.0.1:3306

-- Criar tabela para funcionários e mesas
CREATE TABLE IF NOT EXISTS funcionarios_mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id VARCHAR(10) NOT NULL UNIQUE,
    nome VARCHAR(100) DEFAULT '',
    funcao VARCHAR(100) DEFAULT '',
    status ENUM('ocupada', 'livre') DEFAULT 'livre',
    setor ENUM('Operacional', 'Atendimento') NOT NULL,
    turno VARCHAR(20) DEFAULT 'Manhã',
    horario_inicio TIME DEFAULT '08:00:00',
    horario_fim TIME DEFAULT '17:00:00',
    posicao_x INT DEFAULT 0,
    posicao_y INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir dados da Sala Operacional
INSERT INTO funcionarios_mesas (mesa_id, nome, funcao, status, setor, turno, horario_inicio, horario_fim, posicao_x, posicao_y) VALUES
('OP1', 'Rafael', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 273, 138),
('OP2', 'Liedson', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 387, 141),
('OP3', 'Carla', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 500, 142),
('OP4', 'Jéssica', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 276, 191),
('OP5', 'Jamile', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 386, 195),
('OP6', 'Hevilin', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 502, 194),
('OP7', 'Adriano', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 279, 378),
('OP8', 'Sofia', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 390, 376),
('OP9', 'Maisa', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 502, 377),
('OP10', 'Jaque', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 280, 425),
('OP11', 'Bruna', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 389, 428),
('OP12', 'Alex', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 508, 433),
('OP13', 'Andri', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 595, 442),
('OP14', 'Rodrigo', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 270, 631),
('OP15', 'Margarete', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 374, 626),
('OP16', 'Henrique', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 461, 626),
('OP17', 'Leo', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 548, 677),
('OP18', 'Du', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 552, 788),
('OP19', 'Camile', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 795, 89),
('OP20', 'Lucas', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 858, 91),
('OP21', 'Eduarda', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 795, 205),
('OP22', 'Gabi', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 850, 200),
('OP23', 'Tamires', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 800, 300),
('OP24', 'Karen', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 850, 300),
('OP25', '', '', 'livre', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 800, 400),
('OP26', 'Gisele', 'Funcionário Operacional', 'ocupada', 'Operacional', 'Manhã', '08:00:00', '17:00:00', 850, 400);

-- Inserir dados da Sala Atendimento
INSERT INTO funcionarios_mesas (mesa_id, nome, funcao, status, setor, turno, horario_inicio, horario_fim, posicao_x, posicao_y) VALUES
('AT1', 'Elaine', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1182, 138),
('AT2', 'Milene', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1032, 138),
('AT3', 'Hillary', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 873, 141),
('AT4', 'João', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 702, 133),
('AT5', '', '', 'livre', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 547, 139),
('AT6', 'Evair', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 402, 140),
('AT7', 'Geane', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1182, 387),
('AT8', 'José', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1025, 398),
('AT9', 'Maria', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 873, 400),
('AT10', 'Ana Paula', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 701, 391),
('AT11', 'Lucas', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 529, 387),
('AT12', '', '', 'livre', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 420, 432),
('AT13', '', '', 'livre', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 540, 471),
('AT14', 'Amanda', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 714, 484),
('AT15', 'Erika', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 879, 480),
('AT16', 'Jessica', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1032, 490),
('AT17', 'Janayna', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 1185, 475),
('AT18', 'Kaique', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 251, 176),
('AT19', 'Gabi', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 262, 303),
('AT20', 'Bianca', 'Closer', 'ocupada', 'Atendimento', 'Manhã', '19:00:00', '22:00:00', 135, 490);

-- Criar índices para melhor performance
CREATE INDEX idx_mesa_id ON funcionarios_mesas(mesa_id);
CREATE INDEX idx_setor ON funcionarios_mesas(setor);
CREATE INDEX idx_status ON funcionarios_mesas(status);