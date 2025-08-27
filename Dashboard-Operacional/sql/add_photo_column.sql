-- Script para adicionar coluna photo na tabela users existente
USE u406174804_teste;

-- Adicionar coluna photo se não existir
ALTER TABLE users ADD COLUMN photo VARCHAR(500) AFTER phone;