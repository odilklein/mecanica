-- =============================================
-- BANCO DE DADOS - AUTO MECÂNICA
-- Sistema de Gestão para Auto Mecânica de Veículos
-- =============================================

CREATE DATABASE IF NOT EXISTS mecanica;
USE mecanica;

-- =============================================
-- TABELA: usuarios (autenticação)
-- =============================================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  tipo ENUM('admin','funcionario') DEFAULT 'funcionario',
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: clientes
-- =============================================
CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  cpf VARCHAR(14) UNIQUE,
  telefone VARCHAR(20),
  email VARCHAR(120),
  endereco VARCHAR(255),
  cidade VARCHAR(80),
  observacoes TEXT,
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: veiculos
-- =============================================
CREATE TABLE IF NOT EXISTS veiculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  placa VARCHAR(10) UNIQUE,
  marca VARCHAR(50),
  modelo VARCHAR(60),
  ano YEAR,
  cor VARCHAR(30),
  km_atual INT DEFAULT 0,
  chassi VARCHAR(30),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: pecas (estoque)
-- =============================================
CREATE TABLE IF NOT EXISTS pecas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) UNIQUE,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT,
  categoria VARCHAR(60),
  quantidade INT NOT NULL DEFAULT 0,
  estoque_minimo INT DEFAULT 5,
  preco_compra DECIMAL(10,2) DEFAULT 0.00,
  preco_venda DECIMAL(10,2) DEFAULT 0.00,
  fornecedor VARCHAR(120),
  localizacao VARCHAR(50),
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: servicos (tipos de serviço da oficina)
-- =============================================
CREATE TABLE IF NOT EXISTS servicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT,
  preco_mao_obra DECIMAL(10,2) DEFAULT 0.00,
  tempo_estimado INT COMMENT 'Tempo em minutos',
  ativo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: agendamentos
-- =============================================
CREATE TABLE IF NOT EXISTS agendamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  veiculo_id INT,
  servico_id INT,
  data_agendamento DATE NOT NULL,
  hora TIME NOT NULL,
  status ENUM('agendado','confirmado','cancelado','concluido') DEFAULT 'agendado',
  observacoes TEXT,
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: orcamentos
-- =============================================
CREATE TABLE IF NOT EXISTS orcamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  veiculo_id INT,
  data_orcamento DATE NOT NULL,
  data_validade DATE,
  status ENUM('aberto','aprovado','reprovado','cancelado') DEFAULT 'aberto',
  total_pecas DECIMAL(10,2) DEFAULT 0.00,
  total_mao_obra DECIMAL(10,2) DEFAULT 0.00,
  total_geral DECIMAL(10,2) DEFAULT 0.00,
  desconto DECIMAL(10,2) DEFAULT 0.00,
  observacoes TEXT,
  criado_por INT,
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: orcamento_itens (itens do orçamento)
-- =============================================
CREATE TABLE IF NOT EXISTS orcamento_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  orcamento_id INT NOT NULL,
  tipo ENUM('peca','servico') NOT NULL,
  peca_id INT,
  servico_id INT,
  descricao_item VARCHAR(200),
  quantidade INT DEFAULT 1,
  preco_unitario DECIMAL(10,2) DEFAULT 0.00,
  subtotal DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
  FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL,
  FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABELA: movimentacoes_estoque
-- =============================================
CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
  id INT AUTO_INCREMENT PRIMARY KEY,
  peca_id INT NOT NULL,
  tipo ENUM('entrada','saida','ajuste') NOT NULL,
  quantidade INT NOT NULL,
  motivo VARCHAR(200),
  data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- USUÁRIO PADRÃO (login: admin@mecanica.com / senha: admin123)
-- =============================================
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador', 'admin@mecanica.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeBj9pL0q3vV4hU0C5nKqYkW1iR5Cv3pS', 'admin');

-- =============================================
-- SERVIÇOS PADRÃO
-- =============================================
INSERT INTO servicos (nome, descricao, preco_mao_obra, tempo_estimado) VALUES
('Troca de Óleo', 'Troca de óleo do motor com filtro', 80.00, 60),
('Alinhamento', 'Alinhamento de direção', 70.00, 45),
('Balanceamento', 'Balanceamento das rodas', 50.00, 40),
('Troca de Pastilhas de Freio', 'Substituição de pastilhas de freio dianteiro', 120.00, 90),
('Revisão Geral', 'Revisão completa do veículo', 200.00, 120),
('Troca de Correia Dentada', 'Substituição da correia dentada', 350.00, 180),
('Troca de Bateria', 'Substituição da bateria do veículo', 60.00, 30),
('Scanner de Injeção', 'Diagnóstico eletrônico com scanner', 100.00, 45);
