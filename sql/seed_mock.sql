-- =============================================
-- MOCK DATA - AUTO MECÂNICA
-- Dados de exemplo para demonstração
-- Executar após o schema (sql/mecanica.sql)
-- =============================================

USE mecanica;

-- =============================================
-- CLIENTES (10 registros)
-- =============================================
INSERT INTO clientes (nome, cpf, telefone, email, endereco, cidade) VALUES
('Carlos Alberto Silva', '123.456.789-01', '(11) 99876-5432', 'carlos.silva@email.com', 'Rua das Flores, 123', 'São Paulo'),
('Maria Fernanda Costa', '987.654.321-00', '(11) 98765-4321', 'maria.costa@email.com', 'Av. Paulista, 456', 'São Paulo'),
('João Pedro Santos', '456.789.123-09', '(21) 97654-3210', 'joao.santos@email.com', 'Rua do Porto, 789', 'Rio de Janeiro'),
('Ana Paula Oliveira', '321.654.987-00', '(31) 96543-2109', 'ana.oliveira@email.com', 'Av. Brasil, 1010', 'Belo Horizonte'),
('Roberto Nascimento', '654.321.098-76', '(41) 95432-1098', 'roberto.nasc@email.com', 'Rua XV de Novembro, 222', 'Curitiba'),
('Luciana Ferreira Lima', '111.222.333-44', '(11) 94321-0987', 'luciana.lima@email.com', 'Rua Augusta, 333', 'São Paulo'),
('Fernando Almeida', '555.666.777-88', '(85) 93210-9876', 'fernando.almeida@email.com', 'Av. Beira Mar, 444', 'Fortaleza'),
('Patricia Rodrigues', '999.888.777-66', '(51) 92109-8765', 'patricia.rodrigues@email.com', 'Rua dos Andradas, 555', 'Porto Alegre'),
('Marcos Vinicius Souza', '222.333.444-55', '(71) 91098-7654', 'marcos.souza@email.com', 'Rua Chile, 666', 'Salvador'),
('Camila Borges', '777.888.999-00', '(61) 90987-6543', 'camila.borges@email.com', 'SGAN 601, 777', 'Brasília');

-- =============================================
-- VEÍCULOS (15 registros)
-- =============================================
INSERT INTO veiculos (cliente_id, placa, marca, modelo, ano, cor, km_atual) VALUES
(1, 'ABC-1234', 'Toyota', 'Corolla', 2022, 'Prata', 35000),
(1, 'ABC-5678', 'Honda', 'Civic', 2021, 'Preto', 42000),
(2, 'DEF-4321', 'Volkswagen', 'Golf', 2023, 'Branco', 12000),
(3, 'GHI-8765', 'Chevrolet', 'Onix', 2022, 'Vermelho', 28000),
(4, 'JKL-1357', 'Fiat', 'Pulse', 2023, 'Cinza', 8000),
(5, 'MNO-2468', 'Hyundai', 'HB20', 2021, 'Azul', 55000),
(6, 'PQR-9753', 'Jeep', 'Renegade', 2022, 'Verde', 31000),
(7, 'STU-1470', 'Ford', 'Bronco Sport', 2023, 'Laranja', 5000),
(8, 'VWX-2581', 'Nissan', 'Kicks', 2022, 'Branco', 19000),
(9, 'YZA-3692', 'Toyota', 'Hilux', 2021, 'Prata', 67000),
(2, 'BCD-4813', 'Honda', 'HR-V', 2023, 'Preto', 7500),
(10, 'EFG-5924', 'Volkswagen', 'T-Cross', 2022, 'Vermelho', 22000),
(3, 'HIJ-6135', 'Chevrolet', 'Tracker', 2021, 'Cinza', 44000),
(5, 'KLM-7246', 'Fiat', 'Fastback', 2023, 'Azul', 3000),
(8, 'NOP-8357', 'Hyundai', 'Tucson', 2022, 'Preto', 15000);

-- =============================================
-- PEÇAS / ESTOQUE (20 registros)
-- =============================================
INSERT INTO pecas (codigo, nome, descricao, categoria, quantidade, estoque_minimo, preco_compra, preco_venda, fornecedor, localizacao) VALUES
('OLF-001', 'Óleo Motor 5W30 Sintético 1L', 'Óleo sintético para motores a gasolina', 'Filtros e Fluidos', 120, 20, 28.00, 45.00, 'Distribuidora AutoParts', 'Prateleira A1'),
('OLF-002', 'Filtro de Óleo Toyota Corolla', 'Filtro de óleo original Toyota', 'Filtros e Fluidos', 45, 10, 22.00, 38.00, 'Toyota Peças', 'Prateleira A2'),
('FRE-001', 'Pastilha de Freio Dianteira Universal', 'Pastilha cerâmica dianteira', 'Freios', 30, 8, 85.00, 150.00, 'FreiosMaster', 'Prateleira B1'),
('FRE-002', 'Disco de Freio Dianteiro 280mm', 'Disco ventilado', 'Freios', 12, 5, 120.00, 220.00, 'FreiosMaster', 'Prateleira B2'),
('MOT-001', 'Correia Dentada Kit Completo', 'Kit correia + tensores', 'Motor', 8, 3, 180.00, 320.00, 'CorreiasPro', 'Prateleira C1'),
('MOT-002', 'Vela de Ignição Iridium (4un)', 'Kit 4 velas iridium', 'Motor', 35, 10, 95.00, 160.00, 'Auto Ignição', 'Prateleira C2'),
('ELE-001', 'Bateria 60Ah 480CCA', 'Bateria selada livre de manutenção', 'Elétrica', 15, 5, 280.00, 450.00, 'EnergiaAuto', 'Chão D1'),
('ELE-002', 'Alternador 90A Reforçado', 'Alternador recondicionado garantia 6 meses', 'Elétrica', 4, 2, 350.00, 580.00, 'EnergiaAuto', 'Chão D2'),
('SUS-001', 'Amortecedor Dianteiro (par)', 'Amortecedor hidráulico', 'Suspensão', 10, 4, 160.00, 280.00, 'SuspensãoMax', 'Prateleira E1'),
('SUS-002', 'Mola Dianteira (par)', 'Mola helicoidal aço liga', 'Suspensão', 6, 3, 140.00, 250.00, 'SuspensãoMax', 'Prateleira E2'),
('TRA-001', 'Óleo Transmissão Automática ATF', 'ATF III 1L', 'Transmissão', 25, 8, 35.00, 55.00, 'Distribuidora AutoParts', 'Prateleira F1'),
('TRA-002', 'Junta Homocinética Dianteira', 'Junta para veículos FWD', 'Transmissão', 7, 3, 80.00, 140.00, 'TransPeças', 'Prateleira F2'),
('CAR-001', 'Filtro de Ar Honda Civic', 'Filtro de ar original Honda', 'Filtros e Fluidos', 40, 10, 32.00, 55.00, 'Honda Peças', 'Prateleira A3'),
('CAR-002', 'Filtro de Cabine Universal', 'Filtro antialérgico', 'Filtros e Fluidos', 50, 15, 18.00, 35.00, 'AutoParts', 'Prateleira A4'),
('REJ-001', 'Fluido de Freio DOT4 500ml', 'Fluido sintético DOT4', 'Filtros e Fluidos', 60, 20, 12.00, 25.00, 'Distribuidora AutoParts', 'Prateleira G1'),
('REJ-002', 'Líquido Radiador Concentrado 1L', 'Anticongelante orgânico', 'Filtros e Fluidos', 30, 10, 15.00, 30.00, 'AutoParts', 'Prateleira G2'),
('PNE-001', 'Pneu 205/55R16 91V', 'Pneu aro 16 aço', 'Pneus e Rodas', 20, 4, 280.00, 420.00, 'PneusGigante', 'Chão P1'),
('PNE-002', 'Pneu 185/65R15 88H', 'Pneu aro 15 aço', 'Pneus e Rodas', 16, 4, 220.00, 350.00, 'PneusGigante', 'Chão P2'),
('ACE-001', 'Limpador para-brisa (par)', 'Limpador universal 16"', 'Acessórios', 30, 10, 20.00, 40.00, 'AcessóriosCar', 'Prateleira H1'),
('ACE-002', 'Capa de Banco Universal (4un)', 'Capa neoprene preta', 'Acessórios', 8, 3, 60.00, 120.00, 'AcessóriosCar', 'Chão H2');

-- =============================================
-- SERVIÇOS ADICIONAIS (4 novos além dos 8 do schema)
-- =============================================
INSERT INTO servicos (nome, descricao, preco_mao_obra, tempo_estimado) VALUES
('Troca de Embreagem', 'Substituição completa de embreagem', 450.00, 240),
('Suspensão Dianteira', 'Revisão completa da suspensão dianteira', 180.00, 120),
('Diagnóstico Eletrônico', 'Análise completa com scanner profissional', 120.00, 60),
('Higienização do Ar Condicionado', 'Limpeza e desinfecção do sistema de ar', 90.00, 45);

-- =============================================
-- AGENDAMENTOS (12 registros)
-- =============================================
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
(8, 9, 12, CURDATE() + INTERVAL 7 DAY, '10:00', 'agendado', 'Ar condicionado não gela');

-- =============================================
-- ORÇAMENTOS (8 registros)
-- =============================================
INSERT INTO orcamentos (numero, cliente_id, veiculo_id, data_orcamento, data_validade, status, total_pecas, total_mao_obra, total_geral, desconto, observacoes, criado_por) VALUES
(1, 1, 1, CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY, 'aprovado', 380.00, 80.00, 460.00, 0.00, 'Troca de óleo e filtro', 1),
(2, 2, 3, CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 22 DAY, 'aprovado', 220.00, 70.00, 290.00, 0.00, 'Alinhamento e balanceamento', 1),
(3, 3, 4, CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'aberto', 370.00, 120.00, 490.00, 0.00, 'Pastilhas e disco de freio', 1),
(4, 5, 6, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 27 DAY, 'aberto', 180.00, 350.00, 530.00, 0.00, 'Troca de correia dentada', 1),
(5, 4, 5, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 29 DAY, 'aberto', 85.00, 120.00, 205.00, 0.00, 'Pastilhas dianteiras', 1),
(6, 7, 8, CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 18 DAY, 'reprovado', 450.00, 60.00, 510.00, 0.00, 'Troca de bateria e revisão elétrica', 1),
(7, 9, 10, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 23 DAY, 'aberto', 800.00, 450.00, 1250.00, 50.00, 'Kit embreagem completo', 1),
(8, 10, 12, CURDATE(), CURDATE() + INTERVAL 30 DAY, 'aberto', 160.00, 180.00, 340.00, 0.00, 'Revisão suspensão dianteira', 1);

-- =============================================
-- ITENS DE ORÇAMENTO (18 registros)
-- =============================================
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

-- =============================================
-- MOVIMENTAÇÕES DE ESTOQUE (10 registros)
-- =============================================
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
