import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Background,
  BackgroundVariant,
  Controls,
  MarkerType,
  MiniMap,
  Panel,
  ReactFlow,
  useEdgesState,
  useNodesState,
  type Node,
  type ReactFlowInstance,
} from '@xyflow/react'
import { Info, Sparkles } from 'lucide-react'
import type { DiagramDefinition, DiagramEdge, DiagramNode } from '@/diagrams/types'
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
  const arestas = useMemo(() => routeEdges(diagram.nodes, diagram.edges), [diagram])
  const escuro = useIsDark()
  const containerRef = useRef<HTMLDivElement>(null)
  const [instancia, setInstancia] = useState<ReactFlowInstance<DiagramNode, DiagramEdge> | null>(null)

  // O React Flow controla posições, seleção e arraste localmente.
  const [nodes, setNodes, onNodesChange] = useNodesState<DiagramNode>(diagram.nodes)
  const [edges, setEdges, onEdgesChange] = useEdgesState<DiagramEdge>(arestas)

  // Animações e detalhes.
  const [animado, setAnimado] = useState(() => diagram.edges.some((edge) => edge.animated))
  const [apontado, setApontado] = useState<string | null>(null)
  const [fixado, setFixado] = useState<string | null>(null)
  const foco = apontado ?? fixado
  const noFoco = foco ? diagram.nodes.find((node) => node.id === foco) : undefined

  // Ao trocar de diagrama, recria o estado e reenquadra a visão.
  useEffect(() => {
    setNodes(diagram.nodes)
    setEdges(routeEdges(diagram.nodes, diagram.edges))
    setApontado(null)
    setFixado(null)
    setAnimado(diagram.edges.some((edge) => edge.animated))
    if (instancia) {
      window.setTimeout(() => instancia.fitView({ padding: 0.16 }), 40)
    }
  }, [diagram, instancia, setEdges, setNodes])

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

  // Nós e arestas relacionados ao item em foco ficam acesos; o resto esmaece.
  const vizinhos = useMemo(() => {
    if (!foco) return null
    const ids = new Set<string>([foco])
    edges.forEach((edge) => {
      if (edge.source === foco) ids.add(edge.target)
      if (edge.target === foco) ids.add(edge.source)
    })
    return ids
  }, [foco, edges])

  const nosExibidos = useMemo(
    () =>
      nodes.map((node) => ({
        ...node,
        className: [
          node.className,
          foco === node.id ? 'diagram-focus' : '',
          vizinhos && !vizinhos.has(node.id) ? 'diagram-dim' : '',
        ]
          .filter(Boolean)
          .join(' '),
      })),
    [nodes, foco, vizinhos],
  )

  const arestasExibidas = useMemo(
    () =>
      edges.map((edge) => ({
        ...edge,
        // Mensagens já usam tracejado próprio; animar mudaria o significado.
        animated: edge.type === 'message' ? edge.animated : animado || edge.animated,
        className: [
          edge.className,
          vizinhos && !(vizinhos.has(edge.source) && vizinhos.has(edge.target))
            ? 'diagram-dim'
            : '',
        ]
          .filter(Boolean)
          .join(' '),
      })),
    [edges, animado, vizinhos],
  )

  // Diagramas de sequência não têm arestas animáveis: o tracejado é semântico.
  const podeAnimar = useMemo(() => edges.some((edge) => edge.type !== 'message'), [edges])

  return (
    <div
      className={`diagram-canvas flex flex-col overflow-hidden ${className || 'card'}`}
      style={{ height }}
      aria-label={diagram.title}
    >
      <div ref={containerRef} className="relative min-h-0 w-full flex-1">
        <ReactFlow<DiagramNode, DiagramEdge>
          nodes={nosExibidos}
          edges={arestasExibidas}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          nodeTypes={nodeTypes}
          edgeTypes={edgeTypes}
          fitView
          fitViewOptions={{ padding: 0.16 }}
          minZoom={0.08}
          maxZoom={2.2}
          nodesConnectable={false}
          onNodeMouseEnter={(_, node) =>
            setApontado(node.type === 'note' || node.type === 'layer' ? null : node.id)
          }
          onNodeMouseLeave={() => setApontado(null)}
          onNodeClick={(_, node) => {
            if (node.type === 'note' || node.type === 'layer') return
            setFixado((atual) => (atual === node.id ? null : node.id))
          }}
          onPaneClick={() => setFixado(null)}
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

          <Panel position="top-left" className="!m-3">
            {podeAnimar ? (
              <button
                type="button"
                onClick={() => setAnimado((valor) => !valor)}
                aria-pressed={animado}
                className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold shadow-sm backdrop-blur transition ${
                  animado
                    ? 'border-wine-300 bg-wine-50/95 text-wine-700'
                    : 'border-line bg-surface/95 text-ink-600 hover:border-wine-300 hover:text-wine-700'
                }`}
                title={animado ? 'Pausar animações' : 'Animar as ligações'}
              >
                <Sparkles className="h-3.5 w-3.5" />
                {animado ? 'Animando' : 'Animar'}
              </button>
            ) : null}
          </Panel>

          {noFoco ? (
            <Panel position="top-center" className="!mt-3 w-[min(92%,420px)]">
              <div className="rounded-lg border border-line bg-surface/95 px-3 py-2 text-left shadow-card backdrop-blur">
                <div className="flex items-start gap-2">
                  <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-wine-600" />
                  <div className="min-w-0">
                    <div className="text-[11.5px] font-bold leading-tight text-ink-800">
                      {noFoco.data.label}
                    </div>
                    <p className="mt-0.5 text-[10.5px] leading-snug text-ink-600">
                      {noFoco.data.description ??
                        noFoco.data.subtitle ??
                        noFoco.data.meta ??
                        'Componente do diagrama.'}
                    </p>
                  </div>
                </div>
              </div>
            </Panel>
          ) : null}

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
        </ReactFlow>
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-line bg-surface/95 px-3 py-2">
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
        <span className="ml-auto hidden text-[10px] text-ink-500 sm:inline">
          Passe o mouse ou clique em um nó para ver os detalhes
        </span>
      </div>
    </div>
  )
}
