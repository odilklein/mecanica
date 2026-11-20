import type { DiagramDefinition, DiagramField, DiagramNode, Tone } from './types'
import { makeEdge, makeNode } from './types'

function classe(
  id: string,
  x: number,
  y: number,
  label: string,
  tone: Tone,
  fields: DiagramField[],
  description?: string,
): DiagramNode {
  const altura = 42 + fields.length * 18 + 10
  return makeNode(id, 'class', x, y, { label, tone, fields, description }, 250, altura)
}

const f = (name: string, type: string): DiagramField => ({ name, type })

const nodes: DiagramNode[] = [
  classe('cliente', 0, 0, 'Client', 'teal', [
    f('id', 'int'), f('name', 'string'), f('cpf', 'string'), f('phone', 'string'), f('city', 'string'),
  ], 'Cadastro do cliente: contato, CPF e cidade. O mesmo cliente pode ter vários veículos e ordens.'),
  classe('veiculo', 0, 190, 'Vehicle', 'teal', [
    f('id', 'int'), f('plate', 'string'), f('brand', 'string'), f('model', 'string'), f('year', 'int'), f('current_km', 'int'),
  ], 'Veículo do cliente, com placa, marca, modelo, ano e quilometragem atual.'),
  classe('usuario', 0, 400, 'User', 'gray', [
    f('id', 'int'), f('name', 'string'), f('email', 'string'), f('role', 'enum'), f('is_active', 'bool'),
  ], 'Conta de acesso: nome, e-mail, perfil de permissão e situação ativo/inativo.'),
  classe('os', 320, 120, 'ServiceOrder', 'wine', [
    f('id', 'int'), f('number', 'int'), f('status', 'enum'), f('priority', 'enum'),
    f('initial_complaint', 'text'), f('diagnosis', 'text'), f('entry_date', 'date'),
  ], 'Centro do domínio. Reúne a queixa, o diagnóstico, o status, a prioridade e os vínculos com cliente e veículo.'),
  classe('resp', 650, 0, 'ServiceOrderResponsible', 'blue', [
    f('order_id', 'int'), f('user_id', 'int'), f('assigned_at', 'datetime'),
  ], 'Liga um usuário à OS como responsável pelo atendimento.'),
  classe('tarefa', 650, 150, 'ServiceOrderTask', 'blue', [
    f('id', 'int'), f('description', 'string'), f('status', 'enum'), f('due_date', 'date'),
  ], 'Tarefas da OS, com descrição, status e prazo.'),
  classe('evento', 650, 310, 'ServiceOrderEvent', 'blue', [
    f('id', 'int'), f('type', 'string'), f('description', 'string'), f('created_at', 'datetime'),
  ], 'Histórico da OS. Cada mudança de status grava um evento novo; nada é sobrescrito.'),
  classe('entrega', 650, 460, 'ServiceOrderDelivery', 'blue', [
    f('id', 'int'), f('modality', 'enum'), f('responsible', 'string'), f('exit_km', 'int'),
  ], 'Entrega do veículo: modalidade, responsável e quilometragem de saída.'),
  classe('orcamento', 650, 620, 'Budget', 'amber', [
    f('id', 'int'), f('number', 'int'), f('status', 'enum'), f('discount', 'decimal'), f('total', 'decimal'), f('valid_until', 'date'),
  ], 'Orçamento da OS, com status, desconto, total e validade.'),
  classe('pedido', 650, 810, 'PartOrder', 'green', [
    f('id', 'int'), f('supplier', 'string'), f('status', 'enum'), f('expected_date', 'date'),
  ], 'Pedido de peças a um fornecedor, com status e previsão de entrega.'),
  classe('ticket', 650, 970, 'Ticket', 'sand', [
    f('id', 'int'), f('subject', 'string'), f('status', 'enum'),
  ], 'Canal de conversa vinculado à OS e aos envolvidos no atendimento.'),
  classe('itemOrcamento', 1000, 620, 'BudgetItem', 'amber', [
    f('id', 'int'), f('type', 'enum'), f('description', 'string'), f('quantity', 'decimal'), f('unit_price', 'decimal'), f('subtotal', 'decimal'),
  ], 'Item do orçamento. Pode ser serviço ou peça, com quantidade, preço e subtotal.'),
  classe('itemPedido', 1000, 810, 'PartOrderItem', 'green', [
    f('id', 'int'), f('description', 'string'), f('sku', 'string'), f('quantity', 'int'),
  ], 'Item do pedido de peças, com descrição, SKU e quantidade.'),
  classe('peca', 1000, 970, 'Part', 'green', [
    f('id', 'int'), f('name', 'string'), f('sku', 'string'), f('price', 'decimal'), f('stock', 'int'),
  ], 'Peça do catálogo, com SKU, preço de venda e estoque.'),
  classe('mensagem', 1000, 1140, 'TicketMessage', 'sand', [
    f('id', 'int'), f('message', 'text'), f('kind', 'enum'), f('created_at', 'datetime'),
  ], 'Mensagem do ticket, com autor, tipo e data de envio.'),
]

const edges = [
  makeEdge('cliente', 'veiculo', '1 : N'),
  makeEdge('cliente', 'os', '1 : N'),
  makeEdge('veiculo', 'os', '1 : N'),
  makeEdge('os', 'tarefa', '1 : N'),
  makeEdge('os', 'resp', '1 : N'),
  makeEdge('os', 'evento', '1 : N'),
  makeEdge('os', 'entrega', '1 : 1'),
  makeEdge('os', 'orcamento', '1 : N'),
  makeEdge('orcamento', 'itemOrcamento', '1 : N'),
  makeEdge('os', 'pedido', '1 : N'),
  makeEdge('pedido', 'itemPedido', '1 : N'),
  makeEdge('peca', 'itemPedido', 'referência · 1 : N'),
  makeEdge('os', 'ticket', '1 : N'),
  makeEdge('ticket', 'mensagem', '1 : N'),
  makeEdge('usuario', 'resp', '1 : N'),
  makeEdge('usuario', 'mensagem', '1 : N'),
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
