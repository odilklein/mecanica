import type { DiagramDefinition } from '@/diagrams/types'
import { TONE_SVG } from '@/diagrams/types'

function tamanho(node: DiagramDefinition['nodes'][number]) {
  return {
    w: Number(node.style?.width ?? 160),
    h: Number(node.style?.height ?? 60),
  }
}

function fonte(largura: number, texto: string) {
  const estimado = (largura / Math.max(8, texto.length)) * 1.7
  return Math.max(7, Math.min(15, estimado))
}

export function DiagramMiniature({
  diagram,
  className = '',
}: {
  diagram: DiagramDefinition
  className?: string
}) {
  const caixas = new Map(
    diagram.nodes.map((n) => {
      const { w, h } = tamanho(n)
      return [n.id, { x: n.position.x, y: n.position.y, w, h, tipo: n.type, tone: n.data.tone }]
    }),
  )
  const xs = [...caixas.values()].flatMap((b) => [b.x, b.x + b.w])
  const ys = [...caixas.values()].flatMap((b) => [b.y, b.y + b.h])
  const minX = Math.min(...xs)
  const minY = Math.min(...ys)
  const largura = Math.max(...xs) - minX
  const altura = Math.max(...ys) - minY
  const pad = Math.max(24, Math.round(Math.min(largura, altura) * 0.06))
  const viewBox = `${minX - pad} ${minY - pad} ${largura + pad * 2} ${altura + pad * 2}`

  return (
    <svg
      viewBox={viewBox}
      className={className}
      preserveAspectRatio="xMidYMid meet"
      role="img"
      aria-label={diagram.title}
      fontFamily="ui-sans-serif, system-ui, sans-serif"
    >
      <rect
        x={minX - pad}
        y={minY - pad}
        width={largura + pad * 2}
        height={altura + pad * 2}
        fill="var(--flow-canvas)"
      />

      {diagram.edges.map((edge) => {
        const s = caixas.get(edge.source)
        const t = caixas.get(edge.target)
        if (!s || !t) return null
        const x1 = s.x + s.w / 2
        const x2 = t.x + t.w / 2
        const y =
          typeof edge.data?.y === 'number'
            ? edge.data.y
            : s.y + s.h / 2 + (t.y + t.h / 2 - (s.y + s.h / 2)) / 2
        return (
          <line
            key={edge.id}
            x1={x1}
            y1={y}
            x2={x2}
            y2={y}
            stroke="var(--flow-dot)"
            strokeWidth={2}
            strokeDasharray={edge.data?.dashed ? '6 5' : undefined}
          />
        )
      })}

      {diagram.nodes.map((node) => {
        const b = caixas.get(node.id)
        if (!b) return null
        const cor = TONE_SVG[(b.tone as keyof typeof TONE_SVG) ?? 'blue'] ?? TONE_SVG.blue
        const rotulo = node.data.label
        const cx = b.x + b.w / 2
        const cy = b.y + b.h / 2
        const fs = fonte(b.w, rotulo)
        const texto = (y: number, size = fs, fill = '#1F2933') => (
          <text
            x={cx}
            y={y}
            textAnchor="middle"
            dominantBaseline="middle"
            fontSize={size}
            fontWeight={600}
            fill={fill}
            stroke="#FDFBF7"
            strokeWidth={2.4}
            paintOrder="stroke"
          >
            {rotulo}
          </text>
        )

        if (b.tipo === 'actor') {
          return (
            <g key={node.id}>
              <circle cx={cx} cy={b.y + 30} r={18} fill={cor.fill} stroke={cor.stroke} strokeWidth={1.8} />
              <path
                d={`M ${cx - 20} ${b.y + 74} Q ${cx} ${b.y + 50} ${cx + 20} ${b.y + 74}`}
                fill={cor.fill}
                stroke={cor.stroke}
                strokeWidth={1.6}
              />
              {texto(b.y + 90, 12)}
            </g>
          )
        }

        if (b.tipo === 'lifeline') {
          const larguraCabecalho = Math.min(b.w * 0.74, 120)
          return (
            <g key={node.id}>
              <line
                x1={cx}
                y1={b.y + 22}
                x2={cx}
                y2={b.y + b.h}
                stroke="var(--flow-dot)"
                strokeWidth={2}
                strokeDasharray="5 5"
              />
              <rect
                x={cx - larguraCabecalho / 2}
                y={b.y}
                width={larguraCabecalho}
                height={24}
                rx={5}
                fill={cor.fill}
                stroke={cor.stroke}
                strokeWidth={1.5}
              />
              <text
                x={cx}
                y={b.y + 12.5}
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize={fonte(larguraCabecalho, rotulo)}
                fontWeight={700}
                fill="#1F2933"
              >
                {rotulo}
              </text>
            </g>
          )
        }

        if (b.tipo === 'layer') {
          return (
            <g key={node.id}>
              <rect
                x={b.x}
                y={b.y}
                width={b.w}
                height={b.h}
                rx={10}
                fill="rgb(var(--sand-100))"
                fillOpacity={0.5}
                stroke="var(--flow-dot)"
                strokeDasharray="7 5"
              />
              <text
                x={b.x + 12}
                y={b.y + 16}
                fontSize={10}
                fontWeight={700}
                fill="#6B7280"
                letterSpacing={0.6}
              >
                {rotulo.toUpperCase()}
              </text>
            </g>
          )
        }

        if (b.tipo === 'class' || b.tipo === 'entity') {
          const linhas = Math.min(5, node.data.fields?.length ?? 0)
          return (
            <g key={node.id}>
              <rect
                x={b.x}
                y={b.y}
                width={b.w}
                height={b.h}
                rx={6}
                fill="rgb(var(--surface))"
                stroke={cor.stroke}
                strokeWidth={1.6}
              />
              <rect x={b.x} y={b.y} width={b.w} height={26} rx={6} fill={cor.fill} />
              <rect x={b.x} y={b.y + 18} width={b.w} height={8} fill={cor.fill} />
              <text
                x={cx}
                y={b.y + 13}
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize={fonte(b.w, rotulo)}
                fontWeight={700}
                fill="#1F2933"
              >
                {rotulo}
              </text>
              {Array.from({ length: linhas }).map((_, i) => (
                <line
                  key={i}
                  x1={b.x + 10}
                  y1={b.y + 40 + i * 15}
                  x2={b.x + b.w - 10}
                  y2={b.y + 40 + i * 15}
                  stroke="var(--flow-dot)"
                  strokeWidth={2}
                />
              ))}
            </g>
          )
        }

        if (b.tipo === 'state') {
          return (
            <g key={node.id}>
              <rect
                x={b.x}
                y={b.y}
                width={b.w}
                height={b.h}
                rx={6}
                fill="rgb(var(--surface))"
                stroke="var(--flow-dot)"
                strokeWidth={1.4}
              />
              <rect x={b.x} y={b.y} width={6} height={b.h} rx={3} fill={cor.stroke} />
              <text
                x={b.x + b.w / 2 + 3}
                y={cy}
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize={fonte(b.w - 12, rotulo)}
                fontWeight={700}
                fill="#1F2933"
              >
                {rotulo}
              </text>
            </g>
          )
        }

        return (
          <g key={node.id}>
            <rect
              x={b.x}
              y={b.y}
              width={b.w}
              height={b.h}
              rx={b.tipo === 'usecase' ? b.h / 2 : 6}
              fill={cor.fill}
              stroke={cor.stroke}
              strokeWidth={1.6}
            />
            <text
              x={cx}
              y={cy}
              textAnchor="middle"
              dominantBaseline="middle"
              fontSize={fs}
              fontWeight={600}
              fill="#1F2933"
            >
              {rotulo}
            </text>
          </g>
        )
      })}
    </svg>
  )
}
