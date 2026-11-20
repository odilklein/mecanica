import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Background,
  BackgroundVariant,
  Controls,
  MarkerType,
  MiniMap,
  Panel,
  ReactFlow,
  type Node,
  type ReactFlowInstance,
} from '@xyflow/react'
import type { DiagramDefinition } from '@/diagrams/types'
import { TONE_SVG } from '@/diagrams/types'
import { useIsDark } from '@/lib/useTheme'
import { nodeTypes } from './diagram-nodes'
import { edgeTypes } from './MessageEdge'
import { routeEdges } from '@/diagrams/routing'

export function DiagramCanvas({
  diagram,
  height,
  className = '',
}: {
  diagram: DiagramDefinition
  height: number | string
  className?: string
}) {
  const edges = useMemo(() => routeEdges(diagram.nodes, diagram.edges), [diagram])
  const escuro = useIsDark()
  const containerRef = useRef<HTMLDivElement>(null)
  const [instancia, setInstancia] = useState<ReactFlowInstance | null>(null)

  // Reenquadra quando o container muda de tamanho (fullscreen, modal, resize).
  useEffect(() => {
    const el = containerRef.current
    if (!el || !instancia) return
    const observador = new ResizeObserver(() => {
      instancia.fitView({ padding: 0.16 })
    })
    observador.observe(el)
    return () => observador.disconnect()
  }, [instancia])

  return (
    <div
      ref={containerRef}
      className={`diagram-canvas overflow-hidden ${className || 'card'}`}
      style={{ height }}
      aria-label={diagram.title}
    >
      <ReactFlow
        nodes={diagram.nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        fitView
        fitViewOptions={{ padding: 0.16 }}
        minZoom={0.08}
        maxZoom={2.2}
        nodesConnectable={false}
        proOptions={{ hideAttribution: true }}
        defaultEdgeOptions={{
          type: 'default',
          markerEnd: {
            type: MarkerType.ArrowClosed,
            color: 'var(--flow-edge)',
            width: 18,
            height: 18,
          },
          style: { stroke: 'var(--flow-edge)', strokeWidth: 1.2 },
          labelStyle: { fill: 'var(--flow-text)', fontSize: 11, fontWeight: 600 },
          labelBgStyle: { fill: 'rgb(var(--surface))', fillOpacity: 0.94 },
          labelBgPadding: [5, 2],
          labelBgBorderRadius: 4,
        }}
        onInit={(novaInstancia) => {
          setInstancia(novaInstancia)
          window.setTimeout(() => novaInstancia.fitView({ padding: 0.16 }), 40)
        }}
        style={{ background: 'transparent' }}
      >
        <Background variant={BackgroundVariant.Dots} gap={18} size={1} color="var(--flow-dot)" />
        <Controls showInteractive={false} />
        <MiniMap
          className="hidden sm:block"
          pannable
          zoomable
          nodeColor={(node: Node) => {
            const tone = (node.data?.tone as keyof typeof TONE_SVG) ?? 'blue'
            return escuro ? TONE_SVG[tone].fill : TONE_SVG[tone].stroke
          }}
          nodeStrokeWidth={0}
          nodeBorderRadius={2}
          maskColor={escuro ? 'rgba(29, 24, 20, 0.72)' : 'rgba(246, 240, 231, 0.72)'}
          style={{ background: 'rgb(var(--surface))' }}
        />
        <Panel position="bottom-left" className="!m-3">
          <div className="flex max-w-[70vw] flex-wrap gap-x-3 gap-y-1.5 rounded-lg border border-line bg-surface/95 px-3 py-2 shadow-sm backdrop-blur">
            {diagram.legend.map((item) => {
              const cor = TONE_SVG[item.tone]
              return (
                <span
                  key={item.label}
                  className="inline-flex items-center gap-1.5 text-[10px] font-medium text-ink-600"
                >
                  <span
                    className="h-2.5 w-2.5 rounded-sm"
                    style={{ background: cor.fill, border: `1px solid ${cor.stroke}` }}
                  />
                  {item.label}
                </span>
              )
            })}
          </div>
        </Panel>
      </ReactFlow>
    </div>
  )
}
