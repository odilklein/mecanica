-- Simplificacao: clientes, funcionarios, OS e pedidos de pecas.
-- Execute depois das migracoes anteriores e de um backup.
USE mecanica;

ALTER TABLE usuarios
  ADD COLUMN cpf VARCHAR(14) NULL UNIQUE,
  ADD COLUMN telefone VARCHAR(20) NULL,
  ADD COLUMN salario DECIMAL(10,2) NULL,
  ADD COLUMN data_admissao DATE NULL,
  ADD COLUMN cargo VARCHAR(100) NULL,
  ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1;

UPDATE usuarios SET tipo = 'mecanico' WHERE tipo = 'funcionario';
ALTER TABLE usuarios MODIFY tipo ENUM('admin','secretaria','mecanico_chefe','mecanico') NOT NULL DEFAULT 'mecanico';

CREATE TABLE os_tarefas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  status ENUM('pendente','em_execucao','concluida','bloqueada') NOT NULL DEFAULT 'pendente',
  criado_por INT NOT NULL,
  concluido_por INT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pedido_pecas_itens
  ADD COLUMN observacoes VARCHAR(255) NULL,
  ADD COLUMN recebido_em DATETIME NULL;

CREATE TABLE cliente_servicos_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  os_id INT NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  realizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacoes TEXT NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_os_responsaveis_usuario ON os_responsaveis (usuario_id, concluido_em);
CREATE INDEX idx_os_status ON ordens_servico (status, data_atualizacao);
CREATE INDEX idx_pedidos_status ON pedidos_pecas (status, criado_em);
