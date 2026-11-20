import type { DiagramDefinition } from './types'
import { makeEdge, makeNode } from './types'

function casosDeUso(
  id: string,
  figure: number,
  ator: string,
  itens: string[],
  descricao: string,
): DiagramDefinition {
  const alturaItem = 54
  const gap = 22
  const altura = itens.length * (alturaItem + gap) + 20
  const nodes = [
    makeNode('ator', 'actor', 0, altura / 2 - 50, { label: ator, tone: 'wine' }, 150, 100),
    ...itens.map((label, i) =>
      makeNode(`uc-${i}`, 'usecase', 300, 10 + i * (alturaItem + gap), { label, tone: 'teal' }, 270, alturaItem),
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
    height: Math.max(altura, 420),
    nodes,
    edges,
    legend: [
      { label: 'Ator', tone: 'wine' },
      { label: 'Caso de uso', tone: 'teal' },
    ],
  }
}

export const casosDeUsoDiagrams: DiagramDefinition[] = [
  casosDeUso(
    '01_casos_uso_admin',
    2,
    'Administrador',
    [
      'Autenticar',
      'Gerenciar cadastros',
      'Gerenciar funcionários',
      'Configurar o sistema',
      'Auditar ações',
      'Acompanhar a operação',
      'Emitir relatórios',
      'Usar assistente de IA',
    ],
    'O administrador tem acesso amplo: cadastros, funcionários, configurações, auditoria, operação, relatórios e assistente. As permissões são verificadas no servidor.',
  ),
  casosDeUso(
    '02_casos_uso_secretaria',
    3,
    'Secretaria',
    [
      'Autenticar',
      'Gerenciar clientes e veículos',
      'Abrir ordem de serviço',
      'Emitir orçamento',
      'Solicitar peças',
      'Registrar entrega',
      'Emitir relatórios',
      'Usar assistente de IA',
    ],
    'A secretaria concentra o atendimento: clientes, veículos, abertura de OS, orçamento, peças, entrega, relatórios e assistente.',
  ),
  casosDeUso(
    '03_casos_uso_chefe',
    4,
    'Mecânico-chefe',
    [
      'Autenticar',
      'Abrir ordem de serviço',
      'Registrar diagnóstico',
      'Distribuir tarefas',
      'Alocar equipe',
      'Solicitar peças',
      'Registrar entrega',
      'Emitir relatórios',
    ],
    'O mecânico-chefe acumula a operação técnica e a gestão da equipe: diagnóstico, tarefas, alocação, peças, entrega e relatórios.',
  ),
  casosDeUso(
    '04_casos_uso_mecanico',
    5,
    'Mecânico',
    [
      'Autenticar',
      'Executar ordem de serviço',
      'Atualizar tarefas',
      'Solicitar peças',
      'Conversar em ticket',
      'Usar assistente de IA',
    ],
    'O mecânico executa as OS às quais está vinculado, atualiza tarefas, solicita peças, conversa nos tickets e usa o assistente.',
  ),
]
