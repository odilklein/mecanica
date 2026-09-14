-- Banco completo para testes da Auto Mecanica.
-- Uso recomendado: importe em um banco vazio no phpMyAdmin.
-- Credenciais de teste para todos os usuarios: senha admin123
-- O hash abaixo corresponde a senha de teste definida para este seed.

CREATE DATABASE IF NOT EXISTS if0_42913295_db_mecanica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE if0_42913295_db_mecanica;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE servicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT,
  preco_mao_obra DECIMAL(10,2) DEFAULT 0.00,
  tempo_estimado INT,
  ativo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pecas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) UNIQUE,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT,
  categoria VARCHAR(60),
  quantidade INT NOT NULL DEFAULT 0,
  estoque_minimo INT DEFAULT 0,
  preco_compra DECIMAL(10,2) DEFAULT 0.00,
  preco_venda DECIMAL(10,2) DEFAULT 0.00,
  fornecedor VARCHAR(120),
  localizacao VARCHAR(50),
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE agendamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  veiculo_id INT,
  servico_id INT,
  os_id INT NULL,
  data_agendamento DATE NOT NULL,
  hora TIME NOT NULL,
  status ENUM('agendado','confirmado','cancelado','concluido') DEFAULT 'agendado',
  observacoes TEXT,
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ordens_servico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  agendamento_id INT NULL,
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

ALTER TABLE agendamentos ADD CONSTRAINT fk_agendamento_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL;
ALTER TABLE ordens_servico ADD CONSTRAINT fk_os_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE SET NULL;

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

CREATE TABLE os_tarefas_audios (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tarefa_id BIGINT NOT NULL,
  arquivo VARCHAR(255) NOT NULL,
  duracao_seg INT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tarefa_id) REFERENCES os_tarefas(id) ON DELETE CASCADE
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
  peca_id INT NULL,
  descricao VARCHAR(200) NOT NULL,
  quantidade INT NOT NULL,
  custo_unitario DECIMAL(10,2) NULL,
  recebido INT NOT NULL DEFAULT 0,
  observacoes VARCHAR(255) NULL,
  recebido_em DATETIME NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_pecas(id) ON DELETE CASCADE,
  FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL
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
  entidade_id BIGINT NOT NULL,
  acao VARCHAR(40) NOT NULL,
  dados JSON NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movimentacoes_estoque (
  id INT AUTO_INCREMENT PRIMARY KEY,
  peca_id INT NOT NULL,
  tipo ENUM('entrada','saida','ajuste') NOT NULL,
  quantidade INT NOT NULL,
  motivo VARCHAR(200),
  data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE CASCADE
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
  peca_id INT NULL,
  servico_id INT NULL,
  descricao_item VARCHAR(200),
  quantidade INT DEFAULT 1,
  preco_unitario DECIMAL(10,2) DEFAULT 0,
  subtotal DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
  FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL,
  FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT INTO servicos (id, nome, descricao, preco_mao_obra, tempo_estimado) VALUES
(1, 'Troca de Oleo', 'Troca de oleo e filtro do motor', 80.00, 45),
(2, 'Alinhamento', 'Alinhamento da direcao', 70.00, 60),
(3, 'Balanceamento', 'Balanceamento das rodas', 50.00, 45),
(4, 'Troca de Pastilhas de Freio', 'Substituicao das pastilhas de freio', 120.00, 90),
(5, 'Revisao dos 60.000 km', 'Revisao preventiva completa', 250.00, 180),
(6, 'Troca de Correia Dentada', 'Substituicao da correia dentada e tensores', 350.00, 240),
(7, 'Troca de Bateria', 'Substituicao da bateria do veiculo', 60.00, 30),
(8, 'Diagnostico Eletronico', 'Analise com scanner profissional', 100.00, 60),
(9, 'Troca de Embreagem', 'Substituicao completa de embreagem', 450.00, 240),
(10, 'Suspensão Dianteira', 'Revisão completa da suspensão dianteira', 180.00, 120),
(11, 'Higienização do Ar Condicionado', 'Limpeza e desinfecção do sistema de ar', 90.00, 45);

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

INSERT INTO pecas (id, codigo, nome, descricao, categoria, quantidade, estoque_minimo, preco_compra, preco_venda, fornecedor, localizacao) VALUES
(1, 'OLF-001', 'Óleo Motor 5W30 Sintético 1L', 'Óleo sintético para motores a gasolina', 'Filtros e Fluidos', 120, 20, 28.00, 45.00, 'Distribuidora AutoParts', 'Prateleira A1'),
(2, 'OLF-002', 'Filtro de Óleo Toyota Corolla', 'Filtro de óleo original Toyota', 'Filtros e Fluidos', 45, 10, 22.00, 38.00, 'Toyota Peças', 'Prateleira A2'),
(3, 'FRE-001', 'Pastilha de Freio Dianteira Universal', 'Pastilha cerâmica dianteira', 'Freios', 30, 8, 85.00, 150.00, 'FreiosMaster', 'Prateleira B1'),
(4, 'FRE-002', 'Disco de Freio Dianteiro 280mm', 'Disco ventilado', 'Freios', 12, 5, 120.00, 220.00, 'FreiosMaster', 'Prateleira B2'),
(5, 'MOT-001', 'Correia Dentada Kit Completo', 'Kit correia + tensores', 'Motor', 8, 3, 180.00, 320.00, 'CorreiasPro', 'Prateleira C1'),
(6, 'MOT-002', 'Vela de Ignição Iridium (4un)', 'Kit 4 velas iridium', 'Motor', 35, 10, 95.00, 160.00, 'Auto Ignição', 'Prateleira C2'),
(7, 'ELE-001', 'Bateria 60Ah 480CCA', 'Bateria selada livre de manutenção', 'Elétrica', 15, 5, 280.00, 450.00, 'EnergiaAuto', 'Chão D1'),
(8, 'ELE-002', 'Alternador 90A Reforçado', 'Alternador recondicionado garantia 6 meses', 'Elétrica', 4, 2, 350.00, 580.00, 'EnergiaAuto', 'Chão D2'),
(9, 'SUS-001', 'Amortecedor Dianteiro (par)', 'Amortecedor hidráulico', 'Suspensão', 10, 4, 160.00, 280.00, 'SuspensãoMax', 'Prateleira E1'),
(10, 'SUS-002', 'Mola Dianteira (par)', 'Mola helicoidal aço liga', 'Suspensão', 6, 3, 140.00, 250.00, 'SuspensãoMax', 'Prateleira E2'),
(11, 'TRA-001', 'Óleo Transmissão Automática ATF', 'ATF III 1L', 'Transmissão', 25, 8, 35.00, 55.00, 'Distribuidora AutoParts', 'Prateleira F1'),
(12, 'TRA-002', 'Junta Homocinética Dianteira', 'Junta para veículos FWD', 'Transmissão', 7, 3, 80.00, 140.00, 'TransPeças', 'Prateleira F2'),
(13, 'CAR-001', 'Filtro de Ar Honda Civic', 'Filtro de ar original Honda', 'Filtros e Fluidos', 40, 10, 32.00, 55.00, 'Honda Peças', 'Prateleira A3'),
(14, 'CAR-002', 'Filtro de Cabine Universal', 'Filtro antialérgico', 'Filtros e Fluidos', 50, 15, 18.00, 35.00, 'AutoParts', 'Prateleira A4'),
(15, 'REJ-001', 'Fluido de Freio DOT4 500ml', 'Fluido sintético DOT4', 'Filtros e Fluidos', 60, 20, 12.00, 25.00, 'Distribuidora AutoParts', 'Prateleira G1'),
(16, 'REJ-002', 'Líquido Radiador Concentrado 1L', 'Anticongelante orgânico', 'Filtros e Fluidos', 30, 10, 15.00, 30.00, 'AutoParts', 'Prateleira G2'),
(17, 'PNE-001', 'Pneu 205/55R16 91V', 'Pneu aro 16 aço', 'Pneus e Rodas', 20, 4, 280.00, 420.00, 'PneusGigante', 'Chão P1'),
(18, 'PNE-002', 'Pneu 185/65R15 88H', 'Pneu aro 15 aço', 'Pneus e Rodas', 16, 4, 220.00, 350.00, 'PneusGigante', 'Chão P2'),
(19, 'ACE-001', 'Limpador para-brisa (par)', 'Limpador universal 16"', 'Acessórios', 30, 10, 20.00, 40.00, 'AcessóriosCar', 'Prateleira H1'),
(20, 'ACE-002', 'Capa de Banco Universal (4un)', 'Capa neoprene preta', 'Acessórios', 8, 3, 60.00, 120.00, 'AcessóriosCar', 'Chão H2');

INSERT INTO agendamentos (cliente_id, veiculo_id, servico_id, data_agendamento, hora, status, observacoes) VALUES
(1, 1, 1, CURDATE(), '09:00', 'agendado', 'Troca de óleo periódica'),
(2, 3, 2, CURDATE(), '10:30', 'confirmado', 'Veículo puxando para a direita'),
(3, 4, 3, CURDATE(), '14:00', 'agendado', 'Barulho nas rodas dianteiras'),
(4, 5, 4, CURDATE() + INTERVAL 1 DAY, '08:00', 'agendado', 'Pastilhas com bastante desgaste'),
(5, 6, 5, CURDATE() + INTERVAL 1 DAY, '11:00', 'agendado', 'Revisão dos 60.000km'),
(1, 2, 7, CURDATE() + INTERVAL 2 DAY, '09:30', 'confirmado', 'Bateria fraca, carro não pega de manhã'),
(7, 8, 1, CURDATE() + INTERVAL 2 DAY, '14:00', 'agendado', 'Troca de óleo e filtro'),
(9, 10, 6, CURDATE() + INTERVAL 3 DAY, '08:00', 'agendado', 'Motor fazendo ruído metálico'),
(6, 7, 8, CURDATE() + INTERVAL 3 DAY, '10:00', 'confirmado', 'Scanner acendeu luz de injeção'),
(10, 12, 9, CURDATE() + INTERVAL 4 DAY, '09:00', 'agendado', 'Embreagem patinando'),
(2, 11, 10, CURDATE() + INTERVAL 5 DAY, '13:00', 'agendado', 'Suspeita de amortecedor'),
(8, 9, 11, CURDATE() + INTERVAL 7 DAY, '10:00', 'agendado', 'Ar condicionado não gela');

INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, agendamento_id, status, prioridade, queixa_inicial, diagnostico, observacoes_internas, responsavel_chefe_id, criado_por, data_entrada, data_conclusao) VALUES
(1001, 1, 1, 1, 'entregue', 'normal', 'Troca de oleo e filtro do motor', 'Revisao realizada sem anomalias.', 'Veiculo liberado para o cliente.', 3, 2, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 10 DAY),
(1002, 2, 3, 2, 'pronta_para_entrega', 'alta', 'Veiculo puxando para a direita', 'Alinhamento e balanceamento executados.', 'Aguardando retirada do cliente.', 3, 2, NOW() - INTERVAL 8 DAY, NULL),
(1003, 3, 4, 3, 'em_execucao', 'normal', 'Barulho nas rodas dianteiras', 'Pastilhas e disco dianteiros com desgaste.', 'Substituicao autorizada no orcamento.', 3, 1, NOW() - INTERVAL 5 DAY, NULL),
(1004, 5, 6, 5, 'aguardando_peca', 'alta', 'Revisao dos 60.000 km', 'Correia dentada proxima do limite de uso.', 'Aguardando kit de correia do fornecedor.', 3, 2, NOW() - INTERVAL 3 DAY, NULL),
(1005, 4, 5, 4, 'aguardando_aprovacao', 'normal', 'Pastilhas dianteiras desgastadas', 'Pastilhas abaixo da espessura recomendada.', 'Orcamento enviado para aprovacao.', 3, 2, NOW() - INTERVAL 2 DAY, NULL),
(1006, 7, 8, 7, 'cancelada', 'baixa', 'Troca de bateria e revisao eletrica', 'Bateria sem capacidade de carga.', 'Cliente cancelou o servico.', 3, 2, NOW() - INTERVAL 12 DAY, NULL),
(1007, 9, 10, 8, 'pronta_para_execucao', 'urgente', 'Motor fazendo ruido metalico', 'Correia e tensores precisam ser substituidos.', 'Pecas recebidas e separadas para execucao.', 3, 1, NOW() - INTERVAL 7 DAY, NULL),
(1008, 10, 12, 10, 'aguardando_diagnostico', 'normal', 'Embreagem patinando', NULL, 'Realizar teste de rodagem e diagnostico.', 3, 2, NOW() - INTERVAL 1 DAY, NULL);

UPDATE agendamentos SET os_id = CASE id WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 5 WHEN 5 THEN 4 WHEN 7 THEN 6 WHEN 8 THEN 7 WHEN 10 THEN 8 ELSE os_id END;
UPDATE ordens_servico SET agendamento_id = CASE id WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 5 WHEN 5 THEN 4 WHEN 6 THEN 7 WHEN 7 THEN 8 WHEN 8 THEN 10 ELSE agendamento_id END;

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

INSERT INTO pedido_pecas_itens (pedido_id, peca_id, descricao, quantidade, custo_unitario, recebido, observacoes, recebido_em) VALUES
(1, 5, 'Correia Dentada Kit Completo', 1, 180.00, 0, 'Kit com correia e tensores.', NULL),
(2, 3, 'Pastilha de Freio Dianteira Universal', 2, 85.00, 2, 'Conferido sem avarias.', NOW() - INTERVAL 1 DAY),
(2, 4, 'Disco de Freio Dianteiro 280mm', 1, 120.00, 1, 'Conferido sem avarias.', NOW() - INTERVAL 1 DAY),
(3, 4, 'Disco de Freio Dianteiro 280mm', 1, 120.00, 0, NULL, NULL);

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

INSERT INTO orcamento_itens (orcamento_id, tipo, peca_id, servico_id, descricao_item, quantidade, preco_unitario, subtotal) VALUES
(1, 'peca', 1, NULL, 'Óleo Motor 5W30 Sintético 1L', 4, 45.00, 180.00),
(1, 'peca', 2, NULL, 'Filtro de Óleo Toyota Corolla', 1, 38.00, 38.00),
(1, 'servico', NULL, 1, 'Troca de Óleo', 1, 80.00, 80.00),
(2, 'servico', NULL, 2, 'Alinhamento', 1, 70.00, 70.00),
(2, 'servico', NULL, 3, 'Balanceamento', 1, 50.00, 50.00),
(3, 'peca', 3, NULL, 'Pastilha de Freio Dianteira', 2, 150.00, 300.00),
(3, 'peca', 4, NULL, 'Disco de Freio Dianteiro', 1, 220.00, 220.00),
(3, 'servico', NULL, 4, 'Troca de Pastilhas de Freio', 1, 120.00, 120.00),
(4, 'peca', 5, NULL, 'Correia Dentada Kit Completo', 1, 320.00, 320.00),
(4, 'servico', NULL, 6, 'Troca de Correia Dentada', 1, 350.00, 350.00),
(5, 'peca', 3, NULL, 'Pastilha de Freio Dianteira', 1, 150.00, 150.00),
(5, 'servico', NULL, 4, 'Troca de Pastilhas de Freio', 1, 120.00, 120.00),
(6, 'peca', 7, NULL, 'Bateria 60Ah 480CCA', 1, 450.00, 450.00),
(6, 'servico', NULL, 8, 'Diagnóstico Eletrônico', 1, 100.00, 100.00),
(7, 'peca', NULL, NULL, 'Kit Embreagem Completo', 1, 800.00, 800.00),
(7, 'servico', NULL, 9, 'Troca de Embreagem', 1, 450.00, 450.00),
(8, 'peca', 9, NULL, 'Amortecedor Dianteiro (par)', 1, 280.00, 280.00),
(8, 'servico', NULL, 10, 'Suspensão Dianteira', 1, 180.00, 180.00);

INSERT INTO movimentacoes_estoque (peca_id, tipo, quantidade, motivo) VALUES
(1, 'entrada', 100, 'Compra inicial de estoque'),
(1, 'saida', 4, 'Orçamento #1 - Troca de óleo'),
(2, 'saida', 1, 'Orçamento #1 - Filtro'),
(3, 'entrada', 30, 'Compra de pastilhas'),
(3, 'saida', 3, 'Orçamentos #3 e #5'),
(7, 'entrada', 15, 'Compra de baterias'),
(7, 'saida', 1, 'Orçamento #6'),
(5, 'entrada', 10, 'Compra de correias'),
(5, 'saida', 1, 'Orçamento #4'),
(9, 'ajuste', -2, 'Ajuste por contagem física - 2 unidades danificadas');

SET FOREIGN_KEY_CHECKS = 1;

-- FIM DO SEED DE DEMONSTRAÇÃO
