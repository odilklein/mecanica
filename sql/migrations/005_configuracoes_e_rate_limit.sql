SET NAMES utf8mb4;

-- =============================================
-- MIGRAÇÃO 005 — CONFIGURAÇÕES + RATE LIMIT DE LOGIN
-- -------------------------------------------------
-- 1) configuracoes: chave/valor para Tema, SEO e Módulos
--    (editáveis pelo admin em pages/configuracoes/).
-- 2) login_tentativas: controle de tentativas de login
--    (bloqueio por email + IP após N falhas).
--
-- Aplicar (banco já existente):
--   docker exec -i mecanica-db mysql -umecanica -proot123 mecanica \
--     < sql/migrations/005_configuracoes_e_rate_limit.sql
-- =============================================

CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo VARCHAR(40) NOT NULL,
    chave VARCHAR(80) NOT NULL UNIQUE,
    valor TEXT NULL,
    tipo ENUM('texto','cor','bool','numero','textarea') NOT NULL DEFAULT 'texto',
    rotulo VARCHAR(120) NOT NULL DEFAULT '',
    ajuda VARCHAR(255) NOT NULL DEFAULT '',
    ordem INT NOT NULL DEFAULT 0,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_tentativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_ip (email, ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores padrão. INSERT IGNORE preserva edições feitas pelo admin em
-- re-execuções acidentais da migração (não sobrescreve valores existentes).
INSERT IGNORE INTO configuracoes (grupo, chave, valor, tipo, rotulo, ajuda, ordem) VALUES
    ('tema',    'tema_cor_primaria',      '#b8270f', 'cor',  'Cor primária',              'Cor da marca usada em botões, links e destaques.', 1),
    ('tema',    'tema_cor_primaria_dark', '#b8270f', 'cor',  'Cor primária (tema escuro)', 'Cor da marca aplicada no modo escuro.', 2),
    ('seo',     'seo_titulo',             'Auto Mecânica', 'texto', 'Título do site', 'Aparece na aba do navegador e no título dos resultados de busca.', 1),
    ('seo',     'seo_descricao',          'Sistema de gestão para oficinas mecânicas: ordens de serviço, orçamentos, pedidos de peças e relatórios.', 'textarea', 'Meta description', 'Descrição usada pelos buscadores (recomendado até 160 caracteres).', 2),
    ('seo',     'seo_palavras_chave',     'oficina mecânica, sistema de gestão, ordem de serviço, orçamento', 'texto', 'Palavras-chave', 'Termos separados por vírgula.', 3),
    ('modulos', 'modulo_notificacoes',    '1', 'bool', 'Notificações',  'Exibe o sino de notificações e o polling no topo do sistema.', 1),
    ('modulos', 'modulo_fipe',            '1', 'bool', 'Tabela FIPE',   'Habilita a integração com a Tabela FIPE (catálogo e valores dos veículos).', 2);
