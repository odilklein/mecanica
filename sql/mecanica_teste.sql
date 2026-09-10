-- Banco completo para testes da Auto Mecanica.
-- Uso recomendado: importe em um banco vazio no phpMyAdmin.
-- Credenciais de teste para todos os usuarios: senha admin123
-- O hash abaixo corresponde a senha de teste definida para este seed.

CREATE DATABASE IF NOT EXISTS mecanica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mecanica;
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

-- Usuarios: senha de teste admin123.
INSERT INTO usuarios (id, nome, email, senha, tipo, cpf, telefone, salario, data_admissao, cargo) VALUES
(1, 'Administrador', 'admin@mecanica.com', '$2y$10$.491K1gK9aEtg0umHZfhDeWJ/w80tt5SEJT9rfcHKBDkt5keW.Z6S', 'admin', '111.111.111-11', '(11) 99999-0001', 6500.00, '2020-01-10', 'Administrador'),
(2, 'Mariana Souza', 'secretaria@mecanica.com', '$2y$10$.491K1gK9aEtg0umHZfhDeWJ/w80tt5SEJT9rfcHKBDkt5keW.Z6S', 'secretaria', '222.222.222-22', '(11) 99999-0002', 3200.00, '2022-03-15', 'Secretaria'),
(3, 'Carlos Oliveira', 'chefe@mecanica.com', '$2y$10$.491K1gK9aEtg0umHZfhDeWJ/w80tt5SEJT9rfcHKBDkt5keW.Z6S', 'mecanico_chefe', '333.333.333-33', '(11) 99999-0003', 5800.00, '2019-07-01', 'Mecânico chefe'),
(4, 'Rafael Santos', 'mecanico1@mecanica.com', '$2y$10$.491K1gK9aEtg0umHZfhDeWJ/w80tt5SEJT9rfcHKBDkt5keW.Z6S', 'mecanico', '444.444.444-44', '(11) 99999-0004', 3600.00, '2023-02-20', 'Mecânico'),
(5, 'Joao Pereira', 'mecanico2@mecanica.com', '$2y$10$.491K1gK9aEtg0umHZfhDeWJ/w80tt5SEJT9rfcHKBDkt5keW.Z6S', 'mecanico', '555.555.555-55', '(11) 99999-0005', 3500.00, '2024-05-06', 'Mecânico');

INSERT INTO clientes (id, nome, cpf, telefone, email, endereco, cidade, observacoes) VALUES
(1, 'Ana Paula Mendes', '123.456.789-01', '(11) 98888-1001', 'ana.mendes@email.test', 'Rua das Flores, 120', 'Sao Paulo', 'Prefere contato por WhatsApp.'),
(2, 'Bruno Almeida', '234.567.890-12', '(11) 98888-1002', 'bruno.almeida@email.test', 'Avenida Central, 450', 'Sao Paulo', NULL),
(3, 'Camila Rocha', '345.678.901-23', '(11) 98888-1003', 'camila.rocha@email.test', 'Rua do Bosque, 88', 'Sao Paulo', 'Cliente desde 2023.');

INSERT INTO veiculos (id, cliente_id, placa, marca, modelo, ano, cor, km_atual, chassi) VALUES
(1, 1, 'ABC-1D23', 'Volkswagen', 'Gol', 2018, 'Prata', 84500, '9BWZZZ377VT000001'),
(2, 1, 'DEF-4G56', 'Honda', 'Civic', 2020, 'Preto', 52300, '93HFC2640LZ000002'),
(3, 2, 'GHI-7J89', 'Chevrolet', 'Onix', 2021, 'Branco', 37800, '9BGKS48U0MG000003'),
(4, 3, 'JKL-0M12', 'Fiat', 'Strada', 2019, 'Vermelha', 91200, '9BD358A4XKY000004');

INSERT INTO servicos (id, nome, descricao, preco_mao_obra, tempo_estimado) VALUES
(1, 'Diagnostico geral', 'Analise inicial do veiculo', 120.00, 60),
(2, 'Sistema de arrefecimento', 'Verificacao e reparo do sistema', 280.00, 180),
(3, 'Revisao de freios', 'Inspecao do sistema de freios', 180.00, 120);

INSERT INTO pecas (id, codigo, nome, categoria, quantidade, estoque_minimo, preco_compra, preco_venda, fornecedor) VALUES
(1, 'RAD-GOL-001', 'Radiador Gol 2018', 'Arrefecimento', 0, 0, 480.00, 650.00, 'Auto Pecas Central'),
(2, 'FIL-001', 'Filtro de oleo', 'Filtros', 3, 2, 25.00, 45.00, 'Distribuidora Motor'),
(3, 'PAS-ONX-001', 'Pastilha de freio Onix', 'Freios', 0, 0, 140.00, 220.00, 'Freios Brasil');

INSERT INTO ordens_servico (id, numero, cliente_id, veiculo_id, status, prioridade, queixa_inicial, diagnostico, observacoes_internas, responsavel_chefe_id, criado_por, data_entrada) VALUES
(1, 1001, 1, 1, 'aguardando_peca', 'alta', 'Veiculo aquecendo e perdendo agua.', 'Radiador com vazamento; necessario substituir a peca.', 'Cliente precisa do carro ate o final da semana.', 3, 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1002, 2, 3, 'em_execucao', 'normal', 'Barulho ao frear nas rodas dianteiras.', 'Pastilhas dianteiras desgastadas.', 'Realizar teste de rodagem depois da troca.', 3, 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 1003, 3, 4, 'recebida', 'normal', 'Revisao preventiva antes de viagem.', NULL, 'Fazer avaliacao inicial e listar tarefas.', 3, 3, NOW());

INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por) VALUES
(1, 4, 3),
(1, 5, 3),
(2, 4, 3),
(3, 5, 3);

INSERT INTO os_tarefas (os_id, descricao, status, criado_por, concluido_por, concluido_em) VALUES
(1, 'Confirmar modelo e aplicacao do radiador', 'concluida', 3, 4, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'Instalar radiador novo e testar vazamento', 'bloqueada', 3, NULL, NULL),
(2, 'Remover pastilhas antigas', 'concluida', 3, 4, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(2, 'Instalar pastilhas novas e testar freios', 'em_execucao', 3, NULL, NULL),
(3, 'Fazer inspecao visual completa', 'pendente', 3, NULL, NULL);

INSERT INTO pedidos_pecas (id, os_id, fornecedor, status, previsao_entrega, observacoes, criado_por) VALUES
(1, 1, 'Auto Pecas Central', 'a_caminho', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Pedido urgente para liberar o Gol.', 2),
(2, 2, 'Freios Brasil', 'solicitado', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Aguardando confirmacao de disponibilidade.', 2);

INSERT INTO pedido_pecas_itens (pedido_id, peca_id, descricao, quantidade, recebido) VALUES
(1, 1, 'Radiador Gol 2018', 1, 0),
(2, 3, 'Pastilha de freio Onix', 1, 0);

INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES
(1, 'criacao', NULL, 'recebida', 'Atendimento criado.', 3),
(1, 'diagnostico', 'aguardando_diagnostico', 'aguardando_peca', 'Radiador identificado como necessario.', 3),
(1, 'peca_solicitada', NULL, NULL, 'Pedido de radiador criado.', 2),
(2, 'criacao', NULL, 'recebida', 'Atendimento criado.', 3),
(2, 'status', 'pronta_para_execucao', 'em_execucao', 'Execucao iniciada.', 4),
(3, 'criacao', NULL, 'recebida', 'Atendimento criado.', 3);

INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES
(4, 1, 'peca_a_caminho', 'O radiador da OS 1001 esta a caminho.'),
(5, 3, 'os_atribuida', 'A OS 1003 foi atribuida a voce.');

INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao, realizado_em, observacoes) VALUES
(1, 1, 1, 'Diagnostico do sistema de arrefecimento', DATE_SUB(NOW(), INTERVAL 2 DAY), 'Reparo aguardando chegada da peca.'),
(2, 3, 2, 'Revisao do sistema de freios', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Servico em execucao.');

SET FOREIGN_KEY_CHECKS = 1;

-- Logins de teste:
-- admin@mecanica.com / admin123
-- secretaria@mecanica.com / admin123
-- chefe@mecanica.com / admin123
-- mecanico1@mecanica.com / admin123
-- mecanico2@mecanica.com / admin123
