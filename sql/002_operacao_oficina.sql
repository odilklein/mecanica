-- Migracao de dominio: ordem de servico, eventos, tarefas, pecas sob pedido e entrega.
-- Execute depois de um backup. Esta migracao deve ser aplicada uma unica vez.
USE mecanica;

ALTER TABLE usuarios
  MODIFY tipo ENUM('admin','secretaria','mecanico_chefe','mecanico','funcionario') NOT NULL DEFAULT 'funcionario';

CREATE TABLE ordens_servico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  veiculo_id INT NULL,
  agendamento_id INT NULL,
  status ENUM('recebida','triagem','aguardando_diagnostico','aguardando_orcamento','aguardando_aprovacao','aguardando_peca','pronta_para_execucao','em_execucao','aguardando_retorno','pronta_para_entrega','entregue','cancelada') NOT NULL DEFAULT 'recebida',
  prioridade ENUM('baixa','normal','alta','urgente') NOT NULL DEFAULT 'normal',
  queixa_inicial TEXT NOT NULL,
  diagnostico TEXT NULL,
  observacoes_internas TEXT NULL,
  responsavel_chefe_id INT NULL,
  criado_por INT NOT NULL,
  data_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_conclusao DATETIME NULL,
  data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_os_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_os_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
  CONSTRAINT fk_os_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE SET NULL,
  CONSTRAINT fk_os_chefe FOREIGN KEY (responsavel_chefe_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_os_criador FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE agendamentos ADD COLUMN os_id INT NULL,
  ADD CONSTRAINT fk_agendamento_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL;

ALTER TABLE orcamentos ADD COLUMN os_id INT NULL,
  ADD CONSTRAINT fk_orcamento_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL;

CREATE TABLE os_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  status_anterior VARCHAR(40) NULL,
  status_novo VARCHAR(40) NULL,
  descricao TEXT NULL,
  criado_por INT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evento_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  CONSTRAINT fk_evento_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_responsaveis (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  usuario_id INT NOT NULL,
  atribuido_por INT NOT NULL,
  atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  observacoes TEXT NULL,
  UNIQUE KEY uq_os_responsavel (os_id, usuario_id),
  CONSTRAINT fk_responsavel_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  CONSTRAINT fk_responsavel_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_responsavel_autor FOREIGN KEY (atribuido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedidos_pecas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  fornecedor VARCHAR(120) NULL,
  status ENUM('solicitado','pedido','a_caminho','recebido','cancelado') NOT NULL DEFAULT 'solicitado',
  previsao_entrega DATE NULL,
  observacoes TEXT NULL,
  criado_por INT NOT NULL,
  recebido_por INT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recebido_em DATETIME NULL,
  CONSTRAINT fk_pedido_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id),
  CONSTRAINT fk_pedido_criador FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_pedido_recebedor FOREIGN KEY (recebido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedido_pecas_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  peca_id INT NULL,
  descricao VARCHAR(200) NOT NULL,
  quantidade INT NOT NULL,
  custo_unitario DECIMAL(10,2) NULL,
  recebido INT NOT NULL DEFAULT 0,
  CONSTRAINT ck_pedido_item_quantidade CHECK (quantidade > 0),
  CONSTRAINT fk_pedido_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos_pecas(id) ON DELETE CASCADE,
  CONSTRAINT fk_pedido_item_peca FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE os_entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  modalidade ENUM('retirada','entrega') NOT NULL,
  responsavel VARCHAR(120) NULL,
  destino VARCHAR(255) NULL,
  km_saida INT NULL,
  checklist TEXT NULL,
  observacoes TEXT NULL,
  status ENUM('planejada','em_transito','concluida','tentativa_falha','cancelada') NOT NULL DEFAULT 'planejada',
  criado_por INT NOT NULL,
  concluido_por INT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  CONSTRAINT fk_entrega_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id),
  CONSTRAINT fk_entrega_criador FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_entrega_conclusao FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  os_id INT NULL,
  tipo VARCHAR(50) NOT NULL,
  mensagem VARCHAR(255) NOT NULL,
  lida_em DATETIME NULL,
  criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notificacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_notificacao_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auditoria (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  entidade VARCHAR(60) NOT NULL,
  entidade_id BIGINT NOT NULL,
  acao VARCHAR(40) NOT NULL,
  dados JSON NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
