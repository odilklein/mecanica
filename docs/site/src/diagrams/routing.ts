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
