export type TocItem = {
  level: number
  text: string
  id: string
}

export type Requirement = {
  id: string
  name: string
  priority: 'Essencial' | 'Importante' | 'Desejável'
  description: string
}

export type DocumentContent = {
  slug: string
  title: string
  markdown: string
  toc: TocItem[]
  requirements?: {
    rf: Requirement[]
    rnf: Requirement[]
  }
}

export type Integrante = {
  nome: string
  papel: string
}

export type Identificacao = {
  grupo: string
  sistema: string
  disciplina: string
  professor: string
  semestre: string
  data: string
  titulo: string
  curso: string
  instituicao: string
  ano: string
  integrantes: Integrante[]
}
