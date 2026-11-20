import type { DiagramEdge, DiagramNode } from './types'

/** Roteia as arestas pelos handles mais próximos (direita/esquerda ou baixo/topo). */
export function routeEdges(
  nodes: DiagramNode[],
  edges: DiagramEdge[],
): DiagramEdge[] {
  const mapa = new Map(nodes.map((n) => [n.id, n]))
  return edges.map((edge) => {
    const s = mapa.get(edge.source)
    const t = mapa.get(edge.target)
    if (!s || !t) return edge
    const sw = Number(s.style?.width ?? 160)
    const sh = Number(s.style?.height ?? 60)
    const tw = Number(t.style?.width ?? 160)
    const th = Number(t.style?.height ?? 60)
    const dx = t.position.x + tw / 2 - (s.position.x + sw / 2)
    const dy = t.position.y + th / 2 - (s.position.y + sh / 2)

    // O ator tem um handle no centro do ícone: as associações saem do boneco,
    // não do canto da caixa do nó.
    if (s.type === 'actor' && t.type === 'usecase') {
      return {
        ...edge,
        sourceHandle: 'actor',
        targetHandle: 'll',
      }
    }

    // Mensagens de sequência são horizontais e partem do centro da lifeline,
    // não da borda da caixa do nó. Os handles de topo ficam no eixo central.
    if (edge.type === 'message' || typeof edge.data?.y === 'number') {
      return {
        ...edge,
        sourceHandle: 't',
        targetHandle: 'tt',
      }
    }

    if (Math.abs(dx) >= Math.abs(dy)) {
      return {
        ...edge,
        sourceHandle: dx >= 0 ? 'r' : 'l',
        targetHandle: dx >= 0 ? 'll' : 'rr',
      }
    }
    return {
      ...edge,
      sourceHandle: dy >= 0 ? 'b' : 't',
      targetHandle: dy >= 0 ? 'tt' : 'bb',
    }
  })
}
