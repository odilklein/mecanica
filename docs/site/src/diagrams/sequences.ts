import type { DiagramDefinition, DiagramNode, Tone } from './types'
import { makeEdge, makeNode } from './types'

function lifeline(id: string, x: number, label: string, tone: Tone, altura: number): DiagramNode {
  return makeNode(id, 'lifeline', x, 0, { label, tone }, 190, altura)
}

function msg(source: string, target: string, y: number, label: string, dashed = false) {
  return makeEdge(source, target, label, { type: 'message', y, dashed, suffix: `${y}` })
}

const legenda = [
  { label: 'Ator', tone: 'wine' as Tone },
  { label: 'Interface', tone: 'teal' as Tone },
  { label: 'Controller', tone: 'blue' as Tone },
  { label: 'Serviço', tone: 'green' as Tone },
  { label: 'Banco', tone: 'gray' as Tone },
]

export const sequenciaAbrirOS: DiagramDefinition = {
  id: '06_sequencia_abrir_os',
  figure: 7,
  title: 'Sequência: abertura da OS',
  short: 'Abertura da OS',
  description:
    'O controller valida CSRF e perfil, grava a OS com status received, registra o evento inicial, cria o ticket de comunicação e notifica os envolvidos.',
  category: 'UML',
  height: 580,
  nodes: [
    lifeline('u', 0, 'Secretaria', 'wine', 580),
    lifeline('v', 240, 'Formulário (nova OS)', 'teal', 580),
    lifeline('c', 480, 'ServiceOrderController', 'blue', 580),
    lifeline('t', 760, 'TicketService', 'green', 580),
    lifeline('db', 1000, 'MySQL', 'gray', 580),
  ],
  edges: [
    msg('u', 'v', 90, 'preenche a OS'),
    msg('v', 'c', 130, 'POST /service-orders'),
    msg('c', 'db', 180, 'INSERT service_orders'),
    msg('db', 'c', 220, 'id e número', true),
    msg('c', 'db', 260, 'INSERT service_order_events'),
    msg('c', 'db', 300, 'INSERT responsáveis'),
    msg('c', 't', 340, 'cria ticket da OS'),
    msg('t', 'db', 380, 'INSERT tickets'),
    msg('c', 'db', 420, 'INSERT notifications'),
    msg('c', 'v', 470, 'redireciona', true),
    msg('v', 'u', 510, 'confirmação', true),
  ],
  legend: legenda,
}

export const sequenciaAprovarOrcamento: DiagramDefinition = {
  id: '07_sequencia_aprovar_orcamento',
  figure: 8,
  title: 'Sequência: aprovação do orçamento',
  short: 'Aprovação de orçamento',
  description:
    'A aprovação só é permitida a partir do status open. O controller recalcula os totais no servidor, grava a mudança de status, registra auditoria e notifica o criador e os responsáveis.',
  category: 'UML',
  height: 540,
  nodes: [
    lifeline('u', 0, 'Secretaria', 'wine', 540),
    lifeline('v', 240, 'Tela do orçamento', 'teal', 540),
    lifeline('c', 480, 'BudgetController', 'blue', 540),
    lifeline('db', 760, 'MySQL', 'gray', 540),
    lifeline('n', 1000, 'Notificações', 'green', 540),
  ],
  edges: [
    msg('u', 'v', 90, 'revisa itens e desconto'),
    msg('v', 'c', 130, 'POST /budgets/{id}/status'),
    msg('c', 'db', 180, 'SELECT status e itens'),
    msg('db', 'c', 220, 'orçamento (open)', true),
    msg('c', 'db', 270, 'UPDATE budgets (approved)'),
    msg('c', 'db', 310, 'INSERT audit_logs'),
    msg('c', 'n', 350, 'notifica envolvidos'),
    msg('n', 'db', 390, 'INSERT notifications'),
    msg('c', 'v', 440, 'redireciona', true),
    msg('v', 'u', 480, 'status aprovado', true),
  ],
  legend: legenda,
}

export const sequenciaEntrega: DiagramDefinition = {
  id: '08_sequencia_entrega',
  figure: 9,
  title: 'Sequência: entrega do veículo',
  short: 'Entrega do veículo',
  description:
    'A entrega exige que a OS esteja em ready_for_delivery, que ainda não exista entrega registrada e que o KM de saída não seja menor que o KM atual. A gravação ocorre em transação.',
  category: 'UML',
  height: 580,
  nodes: [
    lifeline('u', 0, 'Secretaria', 'wine', 580),
    lifeline('v', 280, 'Tela da OS', 'teal', 580),
    lifeline('c', 560, 'ServiceOrderController', 'blue', 580),
    lifeline('db', 860, 'MySQL', 'gray', 580),
  ],
  edges: [
    msg('u', 'v', 90, 'registra entrega e KM'),
    msg('v', 'c', 130, 'POST .../delivery'),
    msg('c', 'db', 180, 'SELECT status e KM'),
    msg('db', 'c', 220, 'OS e veículo', true),
    msg('c', 'db', 270, 'BEGIN'),
    msg('c', 'db', 310, 'INSERT delivery'),
    msg('c', 'db', 350, 'UPDATE vehicles'),
    msg('c', 'db', 390, 'UPDATE service_orders'),
    msg('c', 'db', 430, 'INSERT events'),
    msg('c', 'db', 470, 'COMMIT'),
    msg('c', 'v', 520, 'sucesso', true),
  ],
  legend: legenda,
}

export const sequenciaDiagrams = [sequenciaAbrirOS, sequenciaAprovarOrcamento, sequenciaEntrega]
