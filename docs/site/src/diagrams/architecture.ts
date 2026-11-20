import type { DiagramDefinition } from './types'
import { makeEdge, makeNode } from './types'

export const arquiteturaDiagram: DiagramDefinition = {
  id: '13_arquitetura',
  figure: 15,
  title: 'Arquitetura da solução',
  short: 'Camadas e integrações',
  description:
    'Monólito modular em PHP 8.2 servido pelo Apache. O front controller aciona o Kernel, que descobre os módulos Core, Mechanic e Agent. A persistência é MySQL 8.4; as integrações externas são opcionais.',
  category: 'Arquitetura',
  height: 540,
  nodes: [
    makeNode('layer-cliente', 'layer', 0, 0, { label: 'Cliente', tone: 'teal' }, 240, 220, {
      zIndex: -1, draggable: false, selectable: false,
    }),
    makeNode('layer-servidor', 'layer', 270, -20, { label: 'Servidor PHP 8.2 / Apache', tone: 'blue' }, 760, 380, {
      zIndex: -1, draggable: false, selectable: false,
    }),
    makeNode('layer-dados', 'layer', 1070, 0, { label: 'Dados e serviços', tone: 'sand' }, 300, 520, {
      zIndex: -1, draggable: false, selectable: false,
    }),
    makeNode('web', 'component', 30, 70, {
      label: 'Navegador', subtitle: 'HTML + Bootstrap 5 + JS', tone: 'teal', icon: 'monitor',
    }, 180, 90),
    makeNode('fc', 'component', 300, 90, {
      label: 'public/index.php', subtitle: 'front controller', tone: 'blue', icon: 'server',
    }, 190, 90),
    makeNode('kernel', 'component', 520, 80, {
      label: 'Kernel', subtitle: 'sessão · container · router · auth · views', tone: 'blue', icon: 'boxes',
    }, 210, 110),
    makeNode('core', 'component', 780, 10, {
      label: 'Core', subtitle: 'login · dashboard · clientes · auditoria', tone: 'green', icon: 'server',
    }, 210, 90),
    makeNode('mech', 'component', 780, 120, {
      label: 'Mechanic', subtitle: 'OS · orçamentos · peças · pedidos', tone: 'amber', icon: 'workflow',
    }, 210, 90),
    makeNode('agent', 'component', 780, 230, {
      label: 'Agent', subtitle: 'assistente · propostas · anexos', tone: 'wine', icon: 'dot',
    }, 210, 90),
    makeNode('bin', 'component', 520, 250, {
      label: 'Migrations e seeds', subtitle: 'bin/', tone: 'gray', icon: 'database',
    }, 200, 70),
    makeNode('db', 'component', 1100, 40, {
      label: 'MySQL 8.4', subtitle: '39 tabelas', tone: 'gray', icon: 'database',
    }, 240, 80),
    makeNode('viacep', 'component', 1100, 150, { label: 'ViaCEP', tone: 'sand' }, 240, 55),
    makeNode('fipe', 'component', 1100, 215, { label: 'FIPE', tone: 'sand' }, 240, 55),
    makeNode('canal', 'component', 1100, 280, { label: 'Canal da Peça', tone: 'sand' }, 240, 55),
    makeNode('groq', 'component', 1100, 345, { label: 'Groq LLM', tone: 'sand' }, 240, 55),
    makeNode('turnstile', 'component', 1100, 410, { label: 'Turnstile', tone: 'sand' }, 240, 55),
  ],
  edges: [
    makeEdge('web', 'fc', 'HTTP', { animated: true }),
    makeEdge('fc', 'kernel', undefined, { animated: true }),
    makeEdge('kernel', 'core', undefined, { suffix: 'c', animated: true }),
    makeEdge('kernel', 'mech', undefined, { suffix: 'm', animated: true }),
    makeEdge('kernel', 'agent', undefined, { suffix: 'a', animated: true }),
    makeEdge('bin', 'db', undefined, { suffix: 'b' }),
    makeEdge('core', 'db', 'PDO', { suffix: 'cd' }),
    makeEdge('mech', 'db', 'PDO', { suffix: 'md' }),
    makeEdge('agent', 'db', 'PDO', { suffix: 'ad' }),
    makeEdge('core', 'viacep'),
    makeEdge('core', 'turnstile'),
    makeEdge('mech', 'fipe'),
    makeEdge('mech', 'canal'),
    makeEdge('agent', 'groq'),
  ],
  legend: [
    { label: 'Cliente', tone: 'teal' },
    { label: 'Servidor', tone: 'blue' },
    { label: 'Módulos', tone: 'amber' },
    { label: 'Assistente', tone: 'wine' },
    { label: 'Persistência', tone: 'gray' },
    { label: 'Serviços externos', tone: 'sand' },
  ],
}

export const fluxoOSDiagram: DiagramDefinition = {
  id: '14_fluxo_os',
  figure: 10,
  title: 'Fluxo de status da OS',
  short: 'Estados da ordem de serviço',
  description:
    'received → in_analysis → awaiting_approval → awaiting_part → in_progress → ready_for_delivery → delivered. Qualquer estado anterior à entrega pode ser cancelado; delivered e cancelled são finais.',
  category: 'Fluxo',
  height: 580,
  nodes: [
    makeNode('received', 'state', 0, 0, { label: 'Recebida', meta: 'received', tone: 'gray' }, 190, 58),
    makeNode('in_analysis', 'state', 0, 110, { label: 'Em análise', meta: 'in_analysis', tone: 'amber' }, 190, 58),
    makeNode('awaiting_approval', 'state', 0, 220, { label: 'Aguardando aprovação', meta: 'awaiting_approval', tone: 'amber' }, 190, 58),
    makeNode('awaiting_part', 'state', 0, 330, { label: 'Aguardando peça', meta: 'awaiting_part', tone: 'amber' }, 190, 58),
    makeNode('in_progress', 'state', 330, 330, { label: 'Em execução', meta: 'in_progress', tone: 'blue' }, 190, 58),
    makeNode('ready', 'state', 330, 220, { label: 'Pronta para entrega', meta: 'ready_for_delivery', tone: 'green' }, 190, 58),
    makeNode('delivered', 'state', 330, 110, { label: 'Entregue', meta: 'delivered', tone: 'green' }, 190, 58),
    makeNode('cancelled', 'state', 165, 470, { label: 'Cancelada', meta: 'cancelled', tone: 'wine' }, 190, 58),
  ],
  edges: [
    makeEdge('received', 'in_analysis', 'iniciar análise', { animated: true }),
    makeEdge('in_analysis', 'awaiting_approval', 'enviar orçamento', { animated: true }),
    makeEdge('in_analysis', 'in_progress', 'serviço direto'),
    makeEdge('awaiting_approval', 'awaiting_part', 'aguardar peça', { animated: true }),
    makeEdge('awaiting_approval', 'in_progress', 'aprovado'),
    makeEdge('awaiting_part', 'in_progress', 'peça recebida', { animated: true }),
    makeEdge('in_progress', 'ready', 'concluir', { animated: true }),
    makeEdge('ready', 'delivered', 'entregar', { animated: true }),
    makeEdge('in_progress', 'cancelled', 'cancelar'),
  ],
  legend: [
    { label: 'Aguardando', tone: 'amber' },
    { label: 'Em execução', tone: 'blue' },
    { label: 'Concluída', tone: 'green' },
    { label: 'Encerrada', tone: 'wine' },
    { label: 'Inicial', tone: 'gray' },
  ],
}
