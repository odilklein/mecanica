export type Member = {
  name: string
  short: string
  role: string
  focus: string[]
  contribution: string
  initials: string
  photo?: string
  github?: string
}

export const members: Member[] = [
  {
    name: 'Odil Klein da Silva Neto',
    short: 'Odil Klein',
    role: 'Desenvolvimento & Arquitetura',
    focus: ['PHP 8.2', 'MySQL', 'React', 'IA'],
    contribution:
      'Concepção, núcleo do sistema, módulos de negócio, modelagem do banco, integrações e redação técnica.',
    initials: 'OK',
    photo: 'https://github.com/odilklein.png',
    github: 'https://github.com/odilklein',
  },
  {
    name: 'Nícolas da Cunha de Godoy',
    short: 'Nícolas Godoy',
    role: 'Documentação & Requisitos',
    focus: ['Requisitos', 'Textos', 'Revisão'],
    contribution:
      'Levantamento e revisão dos requisitos, conferência do texto e organização da entrega escrita.',
    initials: 'NG',
  },
  {
    name: 'Andreo Rafael',
    short: 'Andreo Rafael',
    role: 'Documentação & Modelagem',
    focus: ['Modelagem', 'Dicionário', 'Revisão'],
    contribution:
      'Revisão do modelo de dados, conferência das tabelas e apoio na montagem dos diagramas.',
    initials: 'AR',
  },
  {
    name: 'Alex Rodrigo Candido',
    short: 'Alex Candido',
    role: 'Documentação & Interfaces',
    focus: ['Interfaces', 'Referências', 'Revisão'],
    contribution:
      'Revisão das telas, conferência das referências e apoio na apresentação do projeto.',
    initials: 'AC',
  },
]
