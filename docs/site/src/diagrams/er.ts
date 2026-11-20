import type { DiagramDefinition, DiagramField, DiagramNode, Tone } from './types'
import { makeEdge, makeNode } from './types'

const f = (name: string, type: string, key?: 'PK' | 'FK'): DiagramField => ({ name, type, key })

function entidade(
  id: string,
  x: number,
  y: number,
  label: string,
  tone: Tone,
  fields: DiagramField[] = [],
  largura = 260,
): DiagramNode {
  const altura = fields.length ? 36 + fields.length * 19 + 8 : 52
  return makeNode(id, 'entity', x, y, { label, tone, fields }, largura, altura)
}

export const erConceitual: DiagramDefinition = {
  id: '09_er_conceitual',
  figure: 11,
  title: 'Modelo conceitual',
  short: 'Entidades e cardinalidades',
  description:
    'O cliente possui veículos e solicita ordens de serviço. Cada OS se desdobra em orçamento, pedido de peças e ticket. A peça participa do pedido. As cardinalidades seguem a notação pé de galinha.',
  category: 'Banco de dados',
  height: 460,
  nodes: [
    entidade('cliente', 0, 0, 'Cliente', 'teal', [], 170),
    entidade('veiculo', 0, 170, 'Veículo', 'teal', [], 170),
    entidade('os', 280, 90, 'Ordem de Serviço', 'wine', [], 200),
    entidade('usuario', 560, 0, 'Usuário', 'gray', [], 170),
    entidade('orcamento', 560, 170, 'Orçamento', 'amber', [], 170),
    entidade('pedido', 560, 340, 'Pedido de Peças', 'green', [], 180),
    entidade('peca', 840, 340, 'Peça', 'green', [], 150),
    entidade('ticket', 280, 340, 'Ticket', 'sand', [], 150),
  ],
  edges: [
    makeEdge('cliente', 'veiculo', '1 : N'),
    makeEdge('cliente', 'os', '1 : N'),
    makeEdge('veiculo', 'os', '1 : N'),
    makeEdge('usuario', 'os', '1 : N'),
    makeEdge('os', 'orcamento', '1 : N'),
    makeEdge('os', 'pedido', '1 : N'),
    makeEdge('os', 'ticket', '1 : N'),
    makeEdge('peca', 'pedido', 'N : N'),
  ],
  legend: [
    { label: 'Cadastros', tone: 'teal' },
    { label: 'Ordem de serviço', tone: 'wine' },
    { label: 'Comercial', tone: 'amber' },
    { label: 'Peças', tone: 'green' },
    { label: 'Atendimento', tone: 'sand' },
    { label: 'Apoio', tone: 'gray' },
  ],
}

export const erCadastros: DiagramDefinition = {
  id: '10_er_logico_cadastros',
  figure: 12,
  title: 'Modelo lógico: cadastros e acesso',
  short: 'Cadastros e acesso',
  description:
    'Clientes possuem veículos; usuários recebem notificações. As configurações são chave-valor. CPF e placa são únicos.',
  category: 'Banco de dados',
  height: 420,
  nodes: [
    entidade('clients', 0, 0, 'clients', 'teal', [
      f('id', 'int', 'PK'), f('name', 'varchar(120)'), f('cpf', 'varchar(14)'), f('phone', 'varchar(20)'), f('zip', 'varchar(10)'), f('is_active', 'tinyint'),
    ]),
    entidade('vehicles', 330, 0, 'vehicles', 'teal', [
      f('id', 'int', 'PK'), f('client_id', 'int', 'FK'), f('plate', 'varchar(10)'), f('brand', 'varchar(80)'), f('model', 'varchar(120)'), f('year', 'smallint'),
    ]),
    entidade('users', 0, 230, 'users', 'gray', [
      f('id', 'int', 'PK'), f('name', 'varchar(100)'), f('email', 'varchar(120)'), f('role', 'enum'), f('is_active', 'tinyint'),
    ]),
    entidade('notif', 330, 230, 'notifications', 'gray', [
      f('id', 'bigint', 'PK'), f('user_id', 'int', 'FK'), f('type', 'varchar(50)'), f('read_at', 'datetime'),
    ]),
    entidade('settings', 660, 0, 'settings', 'gray', [
      f('setting_key', 'varchar(80)', 'PK'), f('setting_value', 'text'),
    ]),
  ],
  edges: [
    makeEdge('clients', 'vehicles', '1 : N'),
    makeEdge('users', 'notif', '1 : N'),
  ],
  legend: [
    { label: 'Cadastros', tone: 'teal' },
    { label: 'Apoio', tone: 'gray' },
  ],
}

export const erOperacao: DiagramDefinition = {
  id: '11_er_logico_operacao',
  figure: 13,
  title: 'Modelo lógico: operação da oficina',
  short: 'Operação da oficina',
  description:
    'A OS concentra tarefas, responsáveis, eventos, entrega e tickets. O histórico é append-only: cada mudança de status gera um evento.',
  category: 'Banco de dados',
  height: 640,
  nodes: [
    entidade('os', 0, 100, 'service_orders', 'wine', [
      f('id', 'int', 'PK'), f('number', 'int'), f('client_id', 'int', 'FK'), f('vehicle_id', 'int', 'FK'), f('status', 'enum'), f('priority', 'enum'), f('entry_date', 'date'),
    ]),
    entidade('tasks', 360, 0, 'service_order_tasks', 'blue', [
      f('id', 'int', 'PK'), f('order_id', 'int', 'FK'), f('description', 'varchar(255)'), f('status', 'enum'),
    ]),
    entidade('resp', 360, 155, 'service_order_responsibles', 'blue', [
      f('id', 'int', 'PK'), f('order_id', 'int', 'FK'), f('user_id', 'int', 'FK'),
    ]),
    entidade('events', 360, 290, 'service_order_events', 'blue', [
      f('id', 'int', 'PK'), f('order_id', 'int', 'FK'), f('type', 'varchar(40)'), f('description', 'varchar(255)'),
    ]),
    entidade('delivery', 360, 425, 'service_order_deliveries', 'blue', [
      f('id', 'int', 'PK'), f('order_id', 'int', 'FK'), f('modality', 'enum'), f('exit_km', 'int'),
    ]),
    entidade('tickets', 730, 290, 'tickets', 'sand', [
      f('id', 'int', 'PK'), f('service_order_id', 'int', 'FK'), f('subject', 'varchar(200)'), f('status', 'enum'),
    ]),
    entidade('msgs', 730, 430, 'ticket_messages', 'sand', [
      f('id', 'bigint', 'PK'), f('ticket_id', 'int', 'FK'), f('user_id', 'int', 'FK'), f('message', 'text'), f('kind', 'enum'),
    ]),
  ],
  edges: [
    makeEdge('os', 'tasks'),
    makeEdge('os', 'resp'),
    makeEdge('os', 'events'),
    makeEdge('os', 'delivery'),
    makeEdge('os', 'tickets'),
    makeEdge('tickets', 'msgs'),
  ],
  legend: [
    { label: 'Ordem de serviço', tone: 'wine' },
    { label: 'Operação', tone: 'blue' },
    { label: 'Atendimento', tone: 'sand' },
  ],
}

export const erDocumentos: DiagramDefinition = {
  id: '12_er_logico_documentos',
  figure: 14,
  title: 'Modelo lógico: orçamentos e pedidos',
  short: 'Orçamentos e pedidos',
  description:
    'Orçamentos e pedidos têm itens próprios, o que permite calcular totais e preservar o histórico do que foi proposto e comprado.',
  category: 'Banco de dados',
  height: 620,
  nodes: [
    entidade('budgets', 0, 0, 'budgets', 'amber', [
      f('id', 'int', 'PK'), f('number', 'int'), f('client_id', 'int', 'FK'), f('service_order_id', 'int', 'FK'), f('status', 'enum'), f('total', 'decimal'),
    ]),
    entidade('items', 360, 0, 'budget_items', 'amber', [
      f('id', 'int', 'PK'), f('budget_id', 'int', 'FK'), f('type', 'enum'), f('description', 'varchar(200)'), f('quantity', 'decimal'), f('subtotal', 'decimal'),
    ]),
    entidade('orders', 0, 230, 'part_orders', 'green', [
      f('id', 'int', 'PK'), f('service_order_id', 'int', 'FK'), f('supplier', 'varchar(120)'), f('status', 'enum'),
    ]),
    entidade('oitems', 360, 230, 'part_order_items', 'green', [
      f('id', 'int', 'PK'), f('part_order_id', 'int', 'FK'), f('description', 'varchar(200)'), f('sku', 'varchar(40)'), f('quantity', 'int'),
    ]),
    entidade('parts', 730, 230, 'parts', 'green', [
      f('id', 'int', 'PK'), f('name', 'varchar(200)'), f('category_id', 'int', 'FK'), f('price', 'decimal'), f('stock', 'int'),
    ]),
    entidade('cats', 730, 430, 'part_categories', 'green', [
      f('id', 'int', 'PK'), f('name', 'varchar(120)'), f('slug', 'varchar(120)'),
    ]),
    entidade('sup', 0, 430, 'suppliers', 'green', [
      f('id', 'int', 'PK'), f('name', 'varchar(160)'), f('document', 'varchar(30)'),
    ]),
  ],
  edges: [
    makeEdge('budgets', 'items'),
    makeEdge('orders', 'oitems'),
    makeEdge('cats', 'parts'),
  ],
  legend: [
    { label: 'Comercial', tone: 'amber' },
    { label: 'Peças e pedidos', tone: 'green' },
  ],
}

export const erDiagrams = [erConceitual, erCadastros, erOperacao, erDocumentos]
