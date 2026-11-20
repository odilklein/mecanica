import type { Edge, Node } from '@xyflow/react'

export type Tone =
  | 'wine'
  | 'teal'
  | 'amber'
  | 'green'
  | 'blue'
  | 'gray'
  | 'sand'

export type DiagramField = {
  name: string
  type?: string
  key?: 'PK' | 'FK'
}

export type NodeData = {
  label: string
  subtitle?: string
  meta?: string
  tone?: Tone
  fields?: DiagramField[]
  icon?: string
} & Record<string, unknown>

export type MessageData = {
  y?: number
  dashed?: boolean
} & Record<string, unknown>

export type DiagramNode = Node<NodeData>
export type DiagramEdge = Edge<MessageData>

export type DiagramCategory = 'UML' | 'Banco de dados' | 'Arquitetura' | 'Fluxo'

export type DiagramDefinition = {
  id: string
  figure: number
  title: string
  short: string
  description: string
  category: DiagramCategory
  height: number
  nodes: DiagramNode[]
  edges: DiagramEdge[]
  legend: { label: string; tone: Tone }[]
}

export const TONE_SVG: Record<Tone, { fill: string; stroke: string }> = {
  wine: { fill: '#F7E4E0', stroke: '#B8270F' },
  teal: { fill: '#E6F2F1', stroke: '#1F6F6B' },
  amber: { fill: '#FDF3E1', stroke: '#B7791F' },
  green: { fill: '#E8F3EC', stroke: '#2F6F4E' },
  blue: { fill: '#E8EEF5', stroke: '#3B5B7A' },
  gray: { fill: '#EEF0F2', stroke: '#6B7280' },
  sand: { fill: '#FFF8E1', stroke: '#D8D0C6' },
}

export function makeNode(
  id: string,
  type: string,
  x: number,
  y: number,
  data: NodeData,
  width: number,
  height: number,
  extra: Partial<DiagramNode> = {},
): DiagramNode {
  return {
    id,
    type,
    position: { x, y },
    data,
    style: { width, height },
    initialWidth: width,
    initialHeight: height,
    ...extra,
  }
}

export function makeEdge(
  source: string,
  target: string,
  label?: string,
  opts: {
    suffix?: string
    dashed?: boolean
    y?: number
    type?: string
    animated?: boolean
  } = {},
): DiagramEdge {
  return {
    id: `${source}->${target}${opts.suffix ? `-${opts.suffix}` : ''}`,
    source,
    target,
    label,
    type: opts.type ?? 'default',
    animated: opts.animated,
    data: { y: opts.y, dashed: opts.dashed },
    labelStyle: { fill: '#1F2933', fontSize: 11, fontWeight: 600 },
    labelBgStyle: { fill: '#FFFFFF', fillOpacity: 0.92 },
    labelBgPadding: [5, 2],
    labelBgBorderRadius: 4,
  }
}
