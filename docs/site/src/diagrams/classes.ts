import type { DiagramDefinition, DiagramField, DiagramNode, Tone } from './types'
import { makeEdge, makeNode } from './types'

function classe(
  id: string,
  x: number,
  y: number,
  label: string,
  tone: Tone,
  fields: DiagramField[],
): DiagramNode {
  const altura = 42 + fields.length * 18 + 10
  return makeNode(id, 'class', x, y, { label, tone, fields }, 250, altura)
}

const f = (name: string, type: string): DiagramField => ({ name, type })

const nodes: DiagramNode[] = [
  classe('cliente', 0, 0, 'Client', 'teal', [
    f('id', 'int'), f('name', 'string'), f('cpf', 'string'), f('phone', 'string'), f('city', 'string'),
  ]),
  classe('veiculo', 0, 190, 'Vehicle', 'teal', [
    f('id', 'int'), f('plate', 'string'), f('brand', 'string'), f('model', 'string'), f('year', 'int'), f('current_km', 'int'),
  ]),
  classe('usuario', 0, 400, 'User', 'gray', [
    f('id', 'int'), f('name', 'string'), f('email', 'string'), f('role', 'enum'), f('is_active', 'bool'),
  ]),
  classe('os', 320, 120, 'ServiceOrder', 'wine', [
    f('id', 'int'), f('number', 'int'), f('status', 'enum'), f('priority', 'enum'),
    f('initial_complaint', 'text'), f('diagnosis', 'text'), f('entry_date', 'date'),
  ]),
  classe('resp', 650, 0, 'ServiceOrderResponsible', 'blue', [
    f('order_id', 'int'), f('user_id', 'int'), f('assigned_at', 'datetime'),
  ]),
  classe('tarefa', 650, 150, 'ServiceOrderTask', 'blue', [
    f('id', 'int'), f('description', 'string'), f('status', 'enum'), f('due_date', 'date'),
  ]),
  classe('evento', 650, 310, 'ServiceOrderEvent', 'blue', [
    f('id', 'int'), f('type', 'string'), f('description', 'string'), f('created_at', 'datetime'),
  ]),
  classe('entrega', 650, 460, 'ServiceOrderDelivery', 'blue', [
    f('id', 'int'), f('modality', 'enum'), f('responsible', 'string'), f('exit_km', 'int'),
  ]),
  classe('orcamento', 650, 620, 'Budget', 'amber', [
    f('id', 'int'), f('number', 'int'), f('status', 'enum'), f('discount', 'decimal'), f('total', 'decimal'), f('valid_until', 'date'),
  ]),
  classe('pedido', 650, 810, 'PartOrder', 'green', [
    f('id', 'int'), f('supplier', 'string'), f('status', 'enum'), f('expected_date', 'date'),
  ]),
  classe('ticket', 650, 970, 'Ticket', 'sand', [
    f('id', 'int'), f('subject', 'string'), f('status', 'enum'),
  ]),
  classe('itemOrcamento', 1000, 620, 'BudgetItem', 'amber', [
    f('id', 'int'), f('type', 'enum'), f('description', 'string'), f('quantity', 'decimal'), f('unit_price', 'decimal'), f('subtotal', 'decimal'),
  ]),
  classe('itemPedido', 1000, 810, 'PartOrderItem', 'green', [
    f('id', 'int'), f('description', 'string'), f('sku', 'string'), f('quantity', 'int'),
  ]),
  classe('peca', 1000, 970, 'Part', 'green', [
    f('id', 'int'), f('name', 'string'), f('sku', 'string'), f('price', 'decimal'), f('stock', 'int'),
  ]),
  classe('mensagem', 1000, 1140, 'TicketMessage', 'sand', [
    f('id', 'int'), f('message', 'text'), f('kind', 'enum'), f('created_at', 'datetime'),
  ]),
]

const edges = [
  makeEdge('cliente', 'veiculo'),
  makeEdge('cliente', 'os'),
  makeEdge('veiculo', 'os'),
  makeEdge('os', 'tarefa'),
  makeEdge('os', 'resp'),
  makeEdge('os', 'evento'),
  makeEdge('os', 'entrega'),
  makeEdge('os', 'orcamento'),
  makeEdge('orcamento', 'itemOrcamento'),
  makeEdge('os', 'pedido'),
  makeEdge('pedido', 'itemPedido'),
  makeEdge('peca', 'itemPedido', 'referência'),
  makeEdge('os', 'ticket'),
  makeEdge('ticket', 'mensagem'),
  makeEdge('usuario', 'resp'),
  makeEdge('usuario', 'mensagem'),
]

export const classesDiagram: DiagramDefinition = {
  id: '05_classes',
  figure: 6,
  title: 'Diagrama de classes',
  short: 'Entidades centrais do domínio',
  description:
    'A ServiceOrder é o centro do modelo: dela dependem tarefas, responsáveis, eventos, entrega, orçamento, pedidos de peças e tickets. O cliente possui veículos; cada veículo acumula ordens de serviço ao longo do tempo.',
  category: 'UML',
  height: 1320,
  nodes,
  edges,
  legend: [
    { label: 'Cadastros', tone: 'teal' },
    { label: 'Ordem de serviço', tone: 'wine' },
    { label: 'Operação', tone: 'blue' },
    { label: 'Comercial', tone: 'amber' },
    { label: 'Peças', tone: 'green' },
    { label: 'Atendimento', tone: 'sand' },
  ],
}
