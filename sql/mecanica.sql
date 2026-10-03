-- Banco completo para testes da Auto Mecanica.
-- Uso recomendado: importe em um banco vazio no phpMyAdmin.
-- Credenciais de teste para todos os usuarios: senha admin123
-- O hash abaixo corresponde a senha de teste definida para este seed.

CREATE DATABASE IF NOT EXISTS mecanica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mecanica;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Objetos de banco (View e Trigger). Precisam ser removidos antes das tabelas.
DROP VIEW IF EXISTS vw_ordens_servico_detalhada;
DROP TRIGGER IF EXISTS trg_os_status_historico;

DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS notificacoes;
DROP TABLE IF EXISTS cliente_servicos_historico;
DROP TABLE IF EXISTS os_entregas;
DROP TABLE IF EXISTS pedido_pecas_itens;
DROP TABLE IF EXISTS pedidos_pecas;
DROP TABLE IF EXISTS os_tarefas;
DROP TABLE IF EXISTS os_responsaveis;
DROP TABLE IF EXISTS os_eventos;
DROP TABLE IF EXISTS orcamento_itens;
DROP TABLE IF EXISTS orcamentos;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS movimentacoes_estoque;
DROP TABLE IF EXISTS pecas;
DROP TABLE IF EXISTS servicos;
DROP TABLE IF EXISTS ordens_servico;
DROP TABLE IF EXISTS fipe_precos;
DROP TABLE IF EXISTS fipe_anos;
DROP TABLE IF EXISTS fipe_modelos;
DROP TABLE IF EXISTS fipe_modelos_base;
DROP TABLE IF EXISTS fipe_marcas_alias;
DROP TABLE IF EXISTS fipe_marcas;
DROP TABLE IF EXISTS veiculos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  tipo ENUM('admin','secretaria','mecanico_chefe','mecanico') NOT NULL DEFAULT 'mecanico',
  cpf VARCHAR(14) UNIQUE,
  telefone VARCHAR(20),
  salario DECIMAL(10,2),
  data_admissao DATE,
  cargo VARCHAR(100),
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  cpf VARCHAR(14) UNIQUE,
  telefone VARCHAR(20),
  email VARCHAR(120),
  endereco VARCHAR(255),
  cidade VARCHAR(80),
  observacoes TEXT,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- CATÁLOGO FIPE (espelho local da Tabela FIPE)
-- Populado por scripts/sync_fipe.php a partir da API Parallelum.
-- =============================================
CREATE TABLE fipe_marcas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fipe_codigo VARCHAR(10) NOT NULL,
  nome VARCHAR(100) NOT NULL,
  nome_exibicao VARCHAR(100) NOT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fipe_marca_codigo (fipe_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fipe_marcas_alias (
  nome_fipe VARCHAR(100) PRIMARY KEY,
  nome_exibicao VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fipe_modelos_base (
  id INT AUTO_INCREMENT PRIMARY KEY,
  marca_id INT NOT NULL,
  nome_base VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_fipe_base (marca_id, nome_base),
  FOREIGN KEY (marca_id) REFERENCES fipe_marcas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fipe_modelos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  base_id INT NOT NULL,
  fipe_codigo VARCHAR(20) NOT NULL,
  nome_versao VARCHAR(150) NOT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fipe_modelo (base_id, fipe_codigo),
  FOREIGN KEY (base_id) REFERENCES fipe_modelos_base(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fipe_precos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  modelo_id INT NOT NULL,
  ano_codigo VARCHAR(20) NOT NULL,
  ano INT NULL,
  combustivel VARCHAR(30) NULL,
  valor DECIMAL(10,2) NULL,
  codigo_fipe VARCHAR(20) NULL,
  mes_referencia VARCHAR(20) NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fipe_preco (modelo_id, ano_codigo),
  FOREIGN KEY (modelo_id) REFERENCES fipe_modelos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fipe_anos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  modelo_id INT NOT NULL,
  ano_codigo VARCHAR(20) NOT NULL,
  ano INT NOT NULL,
  nome VARCHAR(60) NOT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fipe_ano (modelo_id, ano_codigo),
  FOREIGN KEY (modelo_id) REFERENCES fipe_modelos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE veiculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  placa VARCHAR(10) UNIQUE,
  marca VARCHAR(50),
  modelo VARCHAR(60),
  ano YEAR,
  cor VARCHAR(30),
  km_atual INT DEFAULT 0,
  chassi VARCHAR(30),
  marca_id INT NULL,
  modelo_base_id INT NULL,
  modelo_id INT NULL,
  fipe_versao VARCHAR(150) NULL,
  valor_fipe DECIMAL(10,2) NULL,
  fipe_mes_referencia VARCHAR(20) NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (marca_id) REFERENCES fipe_marcas(id) ON DELETE SET NULL,
  FOREIGN KEY (modelo_base_id) REFERENCES fipe_modelos_base(id) ON DELETE SET NULL,
  FOREIGN KEY (modelo_id) REFERENCES fipe_modelos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ordens_servico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  status ENUM('recebida','triagem','aguardando_diagnostico','aguardando_orcamento','aguardando_aprovacao','aguardando_peca','pronta_para_execucao','em_execucao','aguardando_retorno','pronta_para_entrega','entregue','cancelada') NOT NULL DEFAULT 'recebida',
  prioridade ENUM('baixa','normal','alta','urgente') NOT NULL DEFAULT 'normal',
  queixa_inicial TEXT NOT NULL,
  diagnostico TEXT,
  observacoes_internas TEXT,
  responsavel_chefe_id INT NULL,
  criado_por INT NOT NULL,
  data_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_conclusao DATETIME NULL,
  data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (responsavel_chefe_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  status_anterior VARCHAR(40),
  status_novo VARCHAR(40),
  descricao TEXT,
  criado_por INT NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_responsaveis (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  usuario_id INT NOT NULL,
  atribuido_por INT NOT NULL,
  atribuido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  observacoes TEXT,
  UNIQUE KEY uq_os_responsavel (os_id, usuario_id),
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (atribuido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_tarefas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  status ENUM('pendente','em_execucao','concluida','bloqueada') DEFAULT 'pendente',
  criado_por INT NOT NULL,
  concluido_por INT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedidos_pecas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  fornecedor VARCHAR(120),
  status ENUM('solicitado','validado','pedido','a_caminho','recebido','cancelado') DEFAULT 'solicitado',
  previsao_entrega DATE NULL,
  observacoes TEXT,
  criado_por INT NOT NULL,
  recebido_por INT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  recebido_em DATETIME NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  FOREIGN KEY (recebido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedido_pecas_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  descricao VARCHAR(200) NOT NULL,
  quantidade INT NOT NULL,
  custo_unitario DECIMAL(10,2) NULL,
  recebido INT NOT NULL DEFAULT 0,
  observacoes VARCHAR(255) NULL,
  recebido_em DATETIME NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_pecas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  modalidade ENUM('retirada','entrega') NOT NULL,
  responsavel VARCHAR(120),
  destino VARCHAR(255),
  km_saida INT,
  checklist TEXT,
  observacoes TEXT,
  status ENUM('planejada','em_transito','concluida','tentativa_falha','cancelada') DEFAULT 'planejada',
  criado_por INT NOT NULL,
  concluido_por INT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id),
  FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cliente_servicos_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  os_id INT NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  realizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  observacoes TEXT,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  os_id INT NULL,
  tipo VARCHAR(50) NOT NULL,
  mensagem VARCHAR(255) NOT NULL,
  lida_em DATETIME NULL,
  criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auditoria (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  entidade VARCHAR(60) NOT NULL,
  entidade_id BIGINT NULL,
  acao VARCHAR(40) NOT NULL,
  dados JSON NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orcamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  os_id INT NULL,
  data_orcamento DATE NOT NULL,
  data_validade DATE,
  status ENUM('aberto','aprovado','reprovado','cancelado') DEFAULT 'aberto',
  total_pecas DECIMAL(10,2) DEFAULT 0,
  total_mao_obra DECIMAL(10,2) DEFAULT 0,
  total_geral DECIMAL(10,2) DEFAULT 0,
  desconto DECIMAL(10,2) DEFAULT 0,
  observacoes TEXT,
  criado_por INT,
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orcamento_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  orcamento_id INT NOT NULL,
  tipo ENUM('peca','servico') NOT NULL,
  descricao_item VARCHAR(200),
  quantidade INT DEFAULT 1,
  preco_unitario DECIMAL(10,2) DEFAULT 0,
  subtotal DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ÍNDICES de apoio às consultas mais frequentes
-- =============================================
ALTER TABLE ordens_servico       ADD INDEX idx_os_status (status, data_atualizacao);
ALTER TABLE ordens_servico       ADD INDEX idx_os_prioridade (prioridade);
ALTER TABLE os_responsaveis      ADD INDEX idx_os_responsaveis_usuario (usuario_id, concluido_em);
ALTER TABLE os_eventos           ADD INDEX idx_os_eventos_os (os_id, criado_em);
ALTER TABLE pedidos_pecas        ADD INDEX idx_pedidos_status (status, criado_em);
ALTER TABLE notificacoes         ADD INDEX idx_notificacoes_usuario (usuario_id, lida_em);
ALTER TABLE auditoria            ADD INDEX idx_auditoria_data (criado_em);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DADOS DE DEMONSTRAÇÃO (seed)
-- Logins de teste:
-- admin@mecanica.com / admin123
-- secretaria@mecanica.com / admin123
-- chefe@mecanica.com / admin123
-- mecanico1@mecanica.com / admin123
-- mecanico2@mecanica.com / admin123
-- =============================================

INSERT INTO usuarios (id, nome, email, senha, tipo, cpf, telefone, cargo, ativo) VALUES
(1, 'Administrador', 'admin@mecanica.com', '$2y$12$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'admin', '111.111.111-11', '(11) 99999-0001', 'Administrador', 1),
(2, 'Secretaria', 'secretaria@mecanica.com', '$2y$12$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'secretaria', '222.222.222-22', '(11) 99999-0002', 'Secretaria', 1),
(3, 'Chefe da Oficina', 'chefe@mecanica.com', '$2y$12$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'mecanico_chefe', '333.333.333-33', '(11) 99999-0003', 'Mecanico chefe', 1),
(4, 'Mecanico 1', 'mecanico1@mecanica.com', '$2y$12$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'mecanico', '444.444.444-44', '(11) 99999-0004', 'Mecanico', 1),
(5, 'Mecanico 2', 'mecanico2@mecanica.com', '$2y$12$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'mecanico', '555.555.555-55', '(11) 99999-0005', 'Mecanico', 1);

INSERT INTO clientes (id, nome, cpf, telefone, email, endereco, cidade) VALUES
(1, 'Carlos Alberto Silva', '123.456.789-01', '(11) 99876-5432', 'carlos.silva@email.com', 'Rua das Flores, 123', 'São Paulo'),
(2, 'Maria Fernanda Costa', '987.654.321-00', '(11) 98765-4321', 'maria.costa@email.com', 'Av. Paulista, 456', 'São Paulo'),
(3, 'João Pedro Santos', '456.789.123-09', '(21) 97654-3210', 'joao.santos@email.com', 'Rua do Porto, 789', 'Rio de Janeiro'),
(4, 'Ana Paula Oliveira', '321.654.987-00', '(31) 96543-2109', 'ana.oliveira@email.com', 'Av. Brasil, 1010', 'Belo Horizonte'),
(5, 'Roberto Nascimento', '654.321.098-76', '(41) 95432-1098', 'roberto.nasc@email.com', 'Rua XV de Novembro, 222', 'Curitiba'),
(6, 'Luciana Ferreira Lima', '111.222.333-44', '(11) 94321-0987', 'luciana.lima@email.com', 'Rua Augusta, 333', 'São Paulo'),
(7, 'Fernando Almeida', '555.666.777-88', '(85) 93210-9876', 'fernando.almeida@email.com', 'Av. Beira Mar, 444', 'Fortaleza'),
(8, 'Patricia Rodrigues', '999.888.777-66', '(51) 92109-8765', 'patricia.rodrigues@email.com', 'Rua dos Andradas, 555', 'Porto Alegre'),
(9, 'Marcos Vinicius Souza', '222.333.444-55', '(71) 91098-7654', 'marcos.souza@email.com', 'Rua Chile, 666', 'Salvador'),
(10, 'Camila Borges', '777.888.999-00', '(61) 90987-6543', 'camila.borges@email.com', 'SGAN 601, 777', 'Brasília');

INSERT INTO veiculos (id, cliente_id, placa, marca, modelo, ano, cor, km_atual) VALUES
(1, 1, 'ABC-1234', 'Toyota', 'Corolla', 2022, 'Prata', 35000),
(2, 1, 'ABC-5678', 'Honda', 'Civic', 2021, 'Preto', 42000),
(3, 2, 'DEF-4321', 'Volkswagen', 'Golf', 2023, 'Branco', 12000),
(4, 3, 'GHI-8765', 'Chevrolet', 'Onix', 2022, 'Vermelho', 28000),
(5, 4, 'JKL-1357', 'Fiat', 'Pulse', 2023, 'Cinza', 8000),
(6, 5, 'MNO-2468', 'Hyundai', 'HB20', 2021, 'Azul', 55000),
(7, 6, 'PQR-9753', 'Jeep', 'Renegade', 2022, 'Verde', 31000),
(8, 7, 'STU-1470', 'Ford', 'Bronco Sport', 2023, 'Laranja', 5000),
(9, 8, 'VWX-2581', 'Nissan', 'Kicks', 2022, 'Branco', 19000),
(10, 9, 'YZA-3692', 'Toyota', 'Hilux', 2021, 'Prata', 67000),
(11, 2, 'BCD-4813', 'Honda', 'HR-V', 2023, 'Preto', 7500),
(12, 10, 'EFG-5924', 'Volkswagen', 'T-Cross', 2022, 'Vermelho', 22000),
(13, 3, 'HIJ-6135', 'Chevrolet', 'Tracker', 2021, 'Cinza', 44000),
(14, 5, 'KLM-7246', 'Fiat', 'Fastback', 2023, 'Azul', 3000),
(15, 8, 'NOP-8357', 'Hyundai', 'Tucson', 2022, 'Preto', 15000);

INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, status, prioridade, queixa_inicial, diagnostico, observacoes_internas, responsavel_chefe_id, criado_por, data_entrada, data_conclusao) VALUES
(1001, 1, 1, 'entregue', 'normal', 'Troca de oleo e filtro do motor', 'Revisao realizada sem anomalias.', 'Veiculo liberado para o cliente.', 3, 2, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 10 DAY),
(1002, 2, 3, 'pronta_para_entrega', 'alta', 'Veiculo puxando para a direita', 'Alinhamento e balanceamento executados.', 'Aguardando retirada do cliente.', 3, 2, NOW() - INTERVAL 8 DAY, NULL),
(1003, 3, 4, 'em_execucao', 'normal', 'Barulho nas rodas dianteiras', 'Pastilhas e disco dianteiros com desgaste.', 'Substituicao autorizada no orcamento.', 3, 1, NOW() - INTERVAL 5 DAY, NULL),
(1004, 5, 6, 'aguardando_peca', 'alta', 'Revisao dos 60.000 km', 'Correia dentada proxima do limite de uso.', 'Aguardando kit de correia do fornecedor.', 3, 2, NOW() - INTERVAL 3 DAY, NULL),
(1005, 4, 5, 'aguardando_aprovacao', 'normal', 'Pastilhas dianteiras desgastadas', 'Pastilhas abaixo da espessura recomendada.', 'Orcamento enviado para aprovacao.', 3, 2, NOW() - INTERVAL 2 DAY, NULL),
(1006, 7, 8, 'cancelada', 'baixa', 'Troca de bateria e revisao eletrica', 'Bateria sem capacidade de carga.', 'Cliente cancelou o servico.', 3, 2, NOW() - INTERVAL 12 DAY, NULL),
(1007, 9, 10, 'pronta_para_execucao', 'urgente', 'Motor fazendo ruido metalico', 'Correia e tensores precisam ser substituidos.', 'Pecas recebidas e separadas para execucao.', 3, 1, NOW() - INTERVAL 7 DAY, NULL),
(1008, 10, 12, 'aguardando_diagnostico', 'normal', 'Embreagem patinando', NULL, 'Realizar teste de rodagem e diagnostico.', 3, 2, NOW() - INTERVAL 1 DAY, NULL);
INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por, atribuido_em, concluido_em, observacoes) VALUES
(1, 4, 3, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 10 DAY, 'Servico concluido.'),
(2, 4, 3, NOW() - INTERVAL 8 DAY, NULL, 'Conferir liberacao do veiculo.'),
(3, 5, 3, NOW() - INTERVAL 5 DAY, NULL, 'Executar troca de freios.'),
(4, 4, 3, NOW() - INTERVAL 3 DAY, NULL, 'Aguardar recebimento do kit.'),
(5, 5, 3, NOW() - INTERVAL 2 DAY, NULL, 'Aguardar aprovacao.'),
(7, 4, 3, NOW() - INTERVAL 7 DAY, NULL, 'Pecas separadas para execucao.'),
(8, 5, 3, NOW() - INTERVAL 1 DAY, NULL, 'Iniciar diagnostico.');

INSERT INTO os_tarefas (os_id, descricao, status, criado_por, concluido_por, criado_em, concluido_em) VALUES
(1, 'Realizar troca de oleo e filtro', 'concluida', 2, 4, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 11 DAY),
(1, 'Conferir nivel e vazamentos', 'concluida', 2, 4, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 10 DAY),
(2, 'Executar alinhamento', 'concluida', 2, 4, NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 7 DAY),
(2, 'Confirmar retirada com cliente', 'pendente', 2, NULL, NOW() - INTERVAL 8 DAY, NULL),
(3, 'Remover conjunto de freio', 'em_execucao', 1, NULL, NOW() - INTERVAL 5 DAY, NULL),
(4, 'Receber kit de correia dentada', 'bloqueada', 2, NULL, NOW() - INTERVAL 3 DAY, NULL),
(7, 'Separar pecas recebidas', 'concluida', 1, 4, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 1 DAY),
(8, 'Realizar teste de rodagem', 'pendente', 2, NULL, NOW() - INTERVAL 1 DAY, NULL);

INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por, criado_em) VALUES
(1, 'criacao', NULL, 'recebida', 'Atendimento criado', 2, NOW() - INTERVAL 12 DAY),
(1, 'diagnostico', 'aguardando_diagnostico', 'aguardando_orcamento', 'Diagnostico concluido', 4, NOW() - INTERVAL 11 DAY),
(1, 'status', 'pronta_para_entrega', 'entregue', 'Veiculo retirado pelo cliente', 2, NOW() - INTERVAL 10 DAY),
(2, 'criacao', NULL, 'recebida', 'Atendimento criado', 2, NOW() - INTERVAL 8 DAY),
(2, 'status', 'em_execucao', 'pronta_para_entrega', 'Servico concluido', 4, NOW() - INTERVAL 1 DAY),
(3, 'criacao', NULL, 'recebida', 'Atendimento criado', 1, NOW() - INTERVAL 5 DAY),
(3, 'diagnostico', 'aguardando_diagnostico', 'aguardando_orcamento', 'Diagnostico dos freios registrado', 5, NOW() - INTERVAL 4 DAY),
(3, 'status', 'aguardando_aprovacao', 'em_execucao', 'Orcamento aprovado e servico iniciado', 1, NOW() - INTERVAL 1 DAY),
(4, 'peca_solicitada', NULL, NULL, 'Pedido de kit de correia criado', 2, NOW() - INTERVAL 2 DAY),
(4, 'status', 'aguardando_orcamento', 'aguardando_peca', 'OS aguardando recebimento de peca', 2, NOW() - INTERVAL 2 DAY),
(5, 'status', 'aguardando_orcamento', 'aguardando_aprovacao', 'Orcamento enviado para aprovacao', 2, NOW() - INTERVAL 1 DAY),
(7, 'peca_status', NULL, NULL, 'Pedido de pecas recebido', 2, NOW() - INTERVAL 1 DAY),
(7, 'status', 'aguardando_peca', 'pronta_para_execucao', 'Pecas disponiveis para execucao', 2, NOW() - INTERVAL 1 DAY),
(8, 'criacao', NULL, 'recebida', 'Atendimento criado', 2, NOW() - INTERVAL 1 DAY);

INSERT INTO os_entregas (os_id, modalidade, responsavel, destino, km_saida, checklist, observacoes, status, criado_por, concluido_por, criado_em, concluido_em) VALUES
(1, 'retirada', 'Carlos Alberto Silva', NULL, 35000, 'Oleo conferido; ferramentas retiradas; veiculo limpo.', 'Entrega concluida sem ocorrencias.', 'concluida', 2, 2, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY);

UPDATE ordens_servico SET status = 'entregue' WHERE id IN (SELECT os_id FROM os_entregas WHERE status = 'concluida');

INSERT INTO pedidos_pecas (os_id, fornecedor, status, previsao_entrega, observacoes, criado_por, recebido_por, criado_em, recebido_em) VALUES
(4, 'CorreiasPro', 'a_caminho', CURDATE() + INTERVAL 2 DAY, 'Kit completo para revisao da correia dentada.', 2, NULL, NOW() - INTERVAL 2 DAY, NULL),
(7, 'FreiosMaster', 'recebido', CURDATE() - INTERVAL 1 DAY, 'Disco e pastilhas conferidos no recebimento.', 2, 2, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 1 DAY),
(3, 'FreiosMaster', 'solicitado', CURDATE() + INTERVAL 4 DAY, 'Aguardando validacao da secretaria.', 5, NULL, NOW() - INTERVAL 1 DAY, NULL);

INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade, custo_unitario, recebido, observacoes, recebido_em) VALUES
(1, 'Correia Dentada Kit Completo', 1, 180.00, 0, 'Kit com correia e tensores.', NULL),
(2, 'Pastilha de Freio Dianteira Universal', 2, 85.00, 2, 'Conferido sem avarias.', NOW() - INTERVAL 1 DAY),
(2, 'Disco de Freio Dianteiro 280mm', 1, 120.00, 1, 'Conferido sem avarias.', NOW() - INTERVAL 1 DAY),
(3, 'Disco de Freio Dianteiro 280mm', 1, 120.00, 0, NULL, NULL);

INSERT INTO orcamentos (numero, cliente_id, veiculo_id, data_orcamento, data_validade, status, total_pecas, total_mao_obra, total_geral, desconto, observacoes, criado_por) VALUES
(1, 1, 1, CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY, 'aprovado', 380.00, 80.00, 460.00, 0.00, 'Troca de óleo e filtro', 1),
(2, 2, 3, CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 22 DAY, 'aprovado', 220.00, 70.00, 290.00, 0.00, 'Alinhamento e balanceamento', 1),
(3, 3, 4, CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'aberto', 370.00, 120.00, 490.00, 0.00, 'Pastilhas e disco de freio', 1),
(4, 5, 6, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 27 DAY, 'aberto', 180.00, 350.00, 530.00, 0.00, 'Troca de correia dentada', 1),
(5, 4, 5, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 29 DAY, 'aberto', 85.00, 120.00, 205.00, 0.00, 'Pastilhas dianteiras', 1),
(6, 7, 8, CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 18 DAY, 'reprovado', 450.00, 60.00, 510.00, 0.00, 'Troca de bateria e revisão elétrica', 1),
(7, 9, 10, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 23 DAY, 'aberto', 800.00, 450.00, 1250.00, 50.00, 'Kit embreagem completo', 1),
(8, 10, 12, CURDATE(), CURDATE() + INTERVAL 30 DAY, 'aberto', 160.00, 180.00, 340.00, 0.00, 'Revisão suspensão dianteira', 1);

UPDATE orcamentos SET os_id = id;

INSERT INTO orcamento_itens (orcamento_id, tipo, descricao_item, quantidade, preco_unitario, subtotal) VALUES
(1, 'peca', 'Óleo Motor 5W30 Sintético 1L', 4, 45.00, 180.00),
(1, 'peca', 'Filtro de Óleo Toyota Corolla', 1, 38.00, 38.00),
(1, 'servico', 'Troca de Óleo', 1, 80.00, 80.00),
(2, 'servico', 'Alinhamento', 1, 70.00, 70.00),
(2, 'servico', 'Balanceamento', 1, 50.00, 50.00),
(3, 'peca', 'Pastilha de Freio Dianteira', 2, 150.00, 300.00),
(3, 'peca', 'Disco de Freio Dianteiro', 1, 220.00, 220.00),
(3, 'servico', 'Troca de Pastilhas de Freio', 1, 120.00, 120.00),
(4, 'peca', 'Correia Dentada Kit Completo', 1, 320.00, 320.00),
(4, 'servico', 'Troca de Correia Dentada', 1, 350.00, 350.00),
(5, 'peca', 'Pastilha de Freio Dianteira', 1, 150.00, 150.00),
(5, 'servico', 'Troca de Pastilhas de Freio', 1, 120.00, 120.00),
(6, 'peca', 'Bateria 60Ah 480CCA', 1, 450.00, 450.00),
(6, 'servico', 'Diagnóstico Eletrônico', 1, 100.00, 100.00),
(7, 'peca', 'Kit Embreagem Completo', 1, 800.00, 800.00),
(7, 'servico', 'Troca de Embreagem', 1, 450.00, 450.00),
(8, 'peca', 'Amortecedor Dianteiro (par)', 1, 280.00, 280.00),
(8, 'servico', 'Suspensão Dianteira', 1, 180.00, 180.00);

-- =============================================
-- VIEW: vw_ordens_servico_detalhada
-- Reune OS + cliente + veiculo + chefia + responsaveis + contadores.
-- Centraliza o JOIN repetido em index.php e listar.php e o rotulo de status.
-- Observacao de performance: as subconsultas rodam uma vez por OS retornada;
-- com filtro por status/paginacao e pelos indices (FK os_id, unique os_id+usuario_id)
-- o custo e baixo neste volume.
-- =============================================
CREATE OR REPLACE VIEW vw_ordens_servico_detalhada AS
SELECT
    os.id,
    os.numero,
    os.status,
    CASE os.status
        WHEN 'recebida' THEN 'Recebida'
        WHEN 'triagem' THEN 'Triagem'
        WHEN 'aguardando_diagnostico' THEN 'Aguardando diagnóstico'
        WHEN 'aguardando_orcamento' THEN 'Aguardando orçamento'
        WHEN 'aguardando_aprovacao' THEN 'Aguardando aprovação'
        WHEN 'aguardando_peca' THEN 'Aguardando peça'
        WHEN 'pronta_para_execucao' THEN 'Pronta para execução'
        WHEN 'em_execucao' THEN 'Em execução'
        WHEN 'aguardando_retorno' THEN 'Aguardando retorno'
        WHEN 'pronta_para_entrega' THEN 'Pronta para entrega'
        WHEN 'entregue' THEN 'Entregue'
        WHEN 'cancelada' THEN 'Cancelada'
    END AS status_label,
    os.prioridade,
    os.queixa_inicial,
    os.diagnostico,
    os.data_entrada,
    os.data_conclusao,
    c.id AS cliente_id,
    c.nome AS cliente,
    c.telefone AS cliente_telefone,
    v.id AS veiculo_id,
    v.marca,
    v.modelo,
    v.ano,
    v.placa,
    TRIM(CONCAT(COALESCE(v.marca, ''), ' ', COALESCE(v.modelo, ''))) AS veiculo,
    u.nome AS responsavel_chefe,
    (SELECT GROUP_CONCAT(ur.nome ORDER BY ur.nome SEPARATOR ', ')
       FROM os_responsaveis r
       JOIN usuarios ur ON ur.id = r.usuario_id
      WHERE r.os_id = os.id AND r.concluido_em IS NULL) AS responsaveis,
    (SELECT COUNT(*) FROM os_tarefas t WHERE t.os_id = os.id) AS total_tarefas,
    (SELECT COUNT(*) FROM os_tarefas t WHERE t.os_id = os.id AND t.status = 'concluida') AS tarefas_concluidas,
    (SELECT COUNT(*) FROM pedidos_pecas p WHERE p.os_id = os.id AND p.status NOT IN ('recebido', 'cancelado')) AS pedidos_pendentes
FROM ordens_servico os
JOIN clientes c ON c.id = os.cliente_id
LEFT JOIN veiculos v ON v.id = os.veiculo_id
LEFT JOIN usuarios u ON u.id = os.responsavel_chefe_id;

-- =============================================
-- TRIGGER: trg_os_status_historico
-- Automatiza o historico: a cada UPDATE de ordens_servico cujo status muda,
-- grava um evento em os_eventos (quem mudou vem de @usuario_atual; se a
-- conexao nao definir, usa o criador da OS).
-- =============================================
DELIMITER $$
CREATE TRIGGER trg_os_status_historico
AFTER UPDATE ON ordens_servico
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por)
        VALUES (
            NEW.id,
            'status',
            OLD.status,
            NEW.status,
            CONCAT('Status alterado de "', OLD.status, '" para "', NEW.status, '".'),
            COALESCE(@usuario_atual, NEW.criado_por)
        );
    END IF;
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- FIM DO SEED DE DEMONSTRAÇÃO
