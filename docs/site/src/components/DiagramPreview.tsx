import { useEffect, useMemo, useRef, useState } from 'react'
import { Background, BackgroundVariant, MarkerType, ReactFlow, type ReactFlowInstance } from '@xyflow/react'
import type { DiagramDefinition } from '@/diagrams/types'
import { nodeTypes } from './diagram-nodes'
import { edgeTypes } from './MessageEdge'
import { routeEdges } from '@/diagrams/routing'

/** Versão não interativa do diagrama, usada nos cards de preview. */
export function DiagramPreview({ diagram }: { diagram: DiagramDefinition }) {
  const edges = useMemo(() => routeEdges(diagram.nodes, diagram.edges), [diagram])
  const ref = useRef<HTMLDivElement>(null)
  const [instancia, setInstancia] = useState<ReactFlowInstance | null>(null)

  useEffect(() => {
    const el = ref.current
    if (!el || !instancia) return
    const observador = new ResizeObserver(() => {
      instancia.fitView({ padding: 0.12 })
    })
    observador.observe(el)
    return () => observador.disconnect()
  }, [instancia])

  return (
    <div ref={ref} className="pointer-events-none h-full w-full" aria-hidden="true">
      <ReactFlow
        nodes={diagram.nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        fitView
        fitViewOptions={{ padding: 0.12 }}
        minZoom={0.05}
        maxZoom={1.6}
        nodesDraggable={false}
        nodesConnectable={false}
        elementsSelectable={false}
        panOnDrag={false}
        zoomOnScroll={false}
        zoomOnPinch={false}
        zoomOnDoubleClick={false}
        preventScrolling={false}
        proOptions={{ hideAttribution: true }}
        defaultEdgeOptions={{
          type: 'default',
          markerEnd: {
            type: MarkerType.ArrowClosed,
            color: 'var(--flow-edge)',
            width: 14,
            height: 14,
          },
          style: { stroke: 'var(--flow-edge)', strokeWidth: 1.1 },
          labelStyle: { fill: 'var(--flow-text)', fontSize: 11, fontWeight: 600 },
          labelBgStyle: { fill: 'rgb(var(--surface))', fillOpacity: 0.94 },
          labelBgPadding: [4, 2],
          labelBgBorderRadius: 3,
        }}
        onInit={(novaInstancia) => {
          setInstancia(novaInstancia)
          window.setTimeout(() => novaInstancia.fitView({ padding: 0.12 }), 30)
        }}
        style={{ background: 'transparent' }}
      >
        <Background variant={BackgroundVariant.Dots} gap={22} size={1} color="var(--flow-dot)" />
      </ReactFlow>
    </div>
  )
}
