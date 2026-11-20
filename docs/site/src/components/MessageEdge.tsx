import { EdgeLabelRenderer, type EdgeProps } from '@xyflow/react'
import type { DiagramEdge } from '@/diagrams/types'

export function MessageEdge({
  sourceX,
  sourceY,
  targetX,
  targetY,
  label,
  data,
}: EdgeProps<DiagramEdge>) {
  const y = typeof data?.y === 'number' ? data.y : (sourceY + targetY) / 2
  const direction = targetX >= sourceX ? 1 : -1
  const arrowX = targetX - 2 * direction
  const dash = data?.dashed ? '7 5' : undefined
  const meio = (sourceX + targetX) / 2

  return (
    <>
      <path
        d={`M ${sourceX} ${y} L ${arrowX - 7 * direction} ${y}`}
        fill="none"
        stroke="var(--flow-edge)"
        strokeWidth={1.5}
        strokeDasharray={dash}
        strokeLinecap="round"
      />
      <polygon
        points={`${arrowX},${y} ${arrowX - 8 * direction},${y - 4} ${arrowX - 8 * direction},${y + 4}`}
        fill="var(--flow-edge)"
      />
      {label ? (
        <EdgeLabelRenderer>
          <div
            className="pointer-events-none absolute rounded border border-line bg-surface/95 px-1.5 py-0.5 text-[10px] font-semibold leading-tight text-ink-800 shadow-sm"
            style={{
              transform: `translate(-50%, -50%) translate(${meio}px, ${y - 11}px)`,
            }}
          >
            {label}
          </div>
        </EdgeLabelRenderer>
      ) : null}
    </>
  )
}

export const edgeTypes = {
  message: MessageEdge,
}
