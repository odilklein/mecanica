import type { DiagramDefinition } from './types'
import { makeEdge, makeNode } from './types'

type Caso = {
  label: string
  description: string
}

function casosDeUso(
  id: string,
  figure: number,
  ator: string,
  atorDescricao: string,
  itens: Caso[],
  descricao: string,
): DiagramDefinition {
  const alturaItem = 54
  const gap = 22
  const alturaLista = itens.length * (alturaItem + gap)
  const altura = Math.max(alturaLista + 120, 480)
  const topoLista = (altura - alturaLista) / 2 + 20
  const topoSistema = topoLista - 52

  const nodes = [
    // Fronteira do sistema: mostra o que fica dentro do Torque.
    makeNode(
      'sistema',
      'layer',
      240,
      topoSistema,
      { label: `Sistema Torque · ${ator}`, tone: 'sand' },
      340,
      alturaLista + 84,
      { zIndex: -1, draggable: false, selectable: false },
    ),
    makeNode(
      'ator',
      'actor',
      0,
      altura / 2 - 50,
      {
        label: ator,
        tone: 'wine',
        description: atorDescricao,
      },
      150,
      100,
      { draggable: false },
    ),
    ...itens.map((item, i) =>
      makeNode(
        `uc-${i}`,
        'usecase',
        300,
        topoLista + i * (alturaItem + gap),
        {
          label: item.label,
          tone: 'teal',
          badge: `UC${String(i + 1).padStart(2, '0')}`,
          description: item.description,
        },
        270,
        alturaItem,
      ),
    ),
  ]
  const edges = itens.map((_, i) => makeEdge('ator', `uc-${i}`, undefined, { suffix: `${i}` }))
  return {
    id,
    figure,
    title: `Casos de uso: ${ator.toLowerCase()}`,
    short: `Casos de uso do perfil ${ator.toLowerCase()}`,
    description: descricao,
    category: 'UML',
    height: Math.max(altura, 480),
    nodes,
    edges,
    legend: [
      { label: 'Ator', tone: 'wine' },
      { label: 'Caso de uso', tone: 'teal' },
      { label: 'Fronteira do sistema', tone: 'sand' },
    ],
  }
}

export const casosDeUsoDiagrams: DiagramDefinition[] = [
  casosDeUso(
    '01_casos_uso_admin',
    2,
    'Administrador',
    'Perfil com acesso total ao sistema. Pode configurar, auditar e acompanhar toda a operação.',
    [
      {
        label: 'Autenticar',
        description: 'Entrada com e-mail e senha; bloqueio temporário após cinco falhas no mesmo endereço IP.',
      },
      {
        label: 'Gerenciar cadastros',
        description: 'Clientes, veículos, peças, fornecedores e categorias do catálogo.',
      },
      {
        label: 'Gerenciar funcionários',
        description: 'Contas de acesso, perfis de permissão e ativação de usuários.',
      },
      {
        label: 'Configurar o sistema',
        description: 'Dados da oficina, aparência, integrações e preferências gerais.',
      },
      {
        label: 'Auditar ações',
        description: 'Consulta ao registro de quem alterou o quê e em que momento.',
      },
      {
        label: 'Acompanhar a operação',
        description: 'Painel com as OS em andamento, prazos e indicadores do dia.',
      },
      {
        label: 'Emitir relatórios',
        description: 'Faturamento, produtividade e movimento por período.',
      },
      {
        label: 'Usar assistente de IA',
        description: 'Apoio para descrever problemas, montar orçamentos e responder ao cliente.',
      },
    ],
    'O administrador tem acesso amplo: cadastros, funcionários, configurações, auditoria, operação, relatórios e assistente. As permissões são verificadas no servidor.',
  ),
  casosDeUso(
    '02_casos_uso_secretaria',
    3,
    'Secretaria',
    'Perfil de atendimento. Concentra o primeiro contato, o cadastro e o acompanhamento do cliente.',
    [
      {
        label: 'Autenticar',
        description: 'Entrada com e-mail e senha; bloqueio temporário após cinco falhas no mesmo endereço IP.',
      },
      {
        label: 'Gerenciar clientes e veículos',
        description: 'Cadastro, histórico de atendimentos e vínculo entre cliente e veículo.',
      },
      {
        label: 'Abrir ordem de serviço',
        description: 'Registro da queixa, do veículo, do KM de entrada e da prioridade.',
      },
      {
        label: 'Emitir orçamento',
        description: 'Itens, quantidades, desconto e envio para aprovação do cliente.',
      },
      {
        label: 'Solicitar peças',
        description: 'Pedido a fornecedores a partir das peças necessárias para a OS.',
      },
      {
        label: 'Registrar entrega',
        description: 'KM de saída, modalidade e responsável pela entrega do veículo.',
      },
      {
        label: 'Emitir relatórios',
        description: 'Atendimentos, faturamento e movimento por período.',
      },
      {
        label: 'Usar assistente de IA',
        description: 'Apoio para cadastrar dados, descrever a queixa e responder ao cliente.',
      },
    ],
    'A secretaria concentra o atendimento: clientes, veículos, abertura de OS, orçamento, peças, entrega, relatórios e assistente.',
  ),
  casosDeUso(
    '03_casos_uso_chefe',
    4,
    'Mecânico-chefe',
    'Perfil técnico com função de coordenação. Responde pelo diagnóstico e pela distribuição do trabalho.',
    [
      {
        label: 'Autenticar',
        description: 'Entrada com e-mail e senha; bloqueio temporário após cinco falhas no mesmo endereço IP.',
      },
      {
        label: 'Abrir ordem de serviço',
        description: 'Registro da queixa, do veículo, do KM de entrada e da prioridade.',
      },
      {
        label: 'Registrar diagnóstico',
        description: 'Análise técnica, serviços necessários e observações para a execução.',
      },
      {
        label: 'Distribuir tarefas',
        description: 'Criação, priorização e atribuição de tarefas aos mecânicos.',
      },
      {
        label: 'Alocar equipe',
        description: 'Definição dos responsáveis por cada OS e ajuste da agenda da oficina.',
      },
      {
        label: 'Solicitar peças',
        description: 'Pedido a fornecedores a partir das peças necessárias para a OS.',
      },
      {
        label: 'Registrar entrega',
        description: 'KM de saída, modalidade e responsável pela entrega do veículo.',
      },
      {
        label: 'Emitir relatórios',
        description: 'Produtividade da equipe, prazos e serviços executados.',
      },
    ],
    'O mecânico-chefe acumula a operação técnica e a gestão da equipe: diagnóstico, tarefas, alocação, peças, entrega e relatórios.',
  ),
  casosDeUso(
    '04_casos_uso_mecanico',
    5,
    'Mecânico',
    'Perfil de execução. Trabalha apenas nas OS em que está vinculado como responsável.',
    [
      {
        label: 'Autenticar',
        description: 'Entrada com e-mail e senha; bloqueio temporário após cinco falhas no mesmo endereço IP.',
      },
      {
        label: 'Executar ordem de serviço',
        description: 'Acompanhamento das tarefas da OS sob sua responsabilidade.',
      },
      {
        label: 'Atualizar tarefas',
        description: 'Mudança de status e registro do que foi feito em cada tarefa.',
      },
      {
        label: 'Solicitar peças',
        description: 'Pedido a fornecedores a partir das peças necessárias para a OS.',
      },
      {
        label: 'Conversar em ticket',
        description: 'Mensagens com a equipe e o cliente dentro da OS.',
      },
      {
        label: 'Usar assistente de IA',
        description: 'Apoio para registrar o diagnóstico e consultar o histórico do veículo.',
      },
    ],
    'O mecânico executa as OS às quais está vinculado, atualiza tarefas, solicita peças, conversa nos tickets e usa o assistente.',
  ),
]
