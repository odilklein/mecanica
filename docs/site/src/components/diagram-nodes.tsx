import { Handle, Position, type NodeProps } from '@xyflow/react'
import {
  Boxes,
  CircleDot,
  Database,
  Monitor,
  Server,
  UserRound,
  Workflow,
} from 'lucide-react'
import type { DiagramNode, Tone } from '@/diagrams/types'

function Ports() {
  const base = '!h-1.5 !w-1.5 !min-h-0 !min-w-0 !border-0 !bg-transparent !opacity-0'
  return (
    <>
      <Handle id="t" type="source" position={Position.Top} className={base} />
      <Handle id="b" type="source" position={Position.Bottom} className={base} />
      <Handle id="l" type="source" position={Position.Left} className={base} />
      <Handle id="r" type="source" position={Position.Right} className={base} />
      <Handle id="tt" type="target" position={Position.Top} className={base} />
      <Handle id="bb" type="target" position={Position.Bottom} className={base} />
      <Handle id="ll" type="target" position={Position.Left} className={base} />
      <Handle id="rr" type="target" position={Position.Right} className={base} />
    </>
  )
}

const TONE: Record<
  Tone,
  { box: string; border: string; header: string; text: string; bar: string; dot: string }
> = {
  wine: {
    box: 'bg-wine-50 dark:bg-wine-900/40',
    border: 'border-wine-200 dark:border-wine-700',
    header: 'bg-wine-100 text-wine-800 dark:bg-wine-800/60 dark:text-wine-100',
    text: 'text-wine-800 dark:text-wine-100',
    bar: 'bg-wine-500',
    dot: 'bg-wine-500',
  },
  teal: {
    box: 'bg-teal-50 dark:bg-teal-950/40',
    border: 'border-teal-200 dark:border-teal-800',
    header: 'bg-teal-100 text-teal-900 dark:bg-teal-900/60 dark:text-teal-100',
    text: 'text-teal-900 dark:text-teal-100',
    bar: 'bg-teal-600',
    dot: 'bg-teal-600',
  },
  amber: {
    box: 'bg-amber-50 dark:bg-amber-950/40',
    border: 'border-amber-200 dark:border-amber-800',
    header: 'bg-amber-100 text-amber-900 dark:bg-amber-900/60 dark:text-amber-100',
    text: 'text-amber-900 dark:text-amber-100',
    bar: 'bg-amber-600',
    dot: 'bg-amber-500',
  },
  green: {
    box: 'bg-emerald-50 dark:bg-emerald-950/40',
    border: 'border-emerald-200 dark:border-emerald-800',
    header: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-100',
    text: 'text-emerald-900 dark:text-emerald-100',
    bar: 'bg-emerald-600',
    dot: 'bg-emerald-600',
  },
  blue: {
    box: 'bg-slate-50 dark:bg-slate-900/50',
    border: 'border-slate-300 dark:border-slate-700',
    header: 'bg-slate-100 text-slate-800 dark:bg-slate-800/70 dark:text-slate-100',
    text: 'text-slate-800 dark:text-slate-100',
    bar: 'bg-slate-600',
    dot: 'bg-slate-600',
  },
  gray: {
    box: 'bg-slate-50 dark:bg-slate-900/40',
    border: 'border-slate-200 dark:border-slate-800',
    header: 'bg-slate-100 text-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
    text: 'text-slate-700 dark:text-slate-200',
    bar: 'bg-slate-400',
    dot: 'bg-slate-400',
  },
  sand: {
    box: 'bg-sand-50 dark:bg-sand-100',
    border: 'border-sand-300 dark:border-sand-300',
    header: 'bg-sand-100 text-ink-700 dark:bg-sand-200 dark:text-ink-800',
    text: 'text-ink-700 dark:text-ink-800',
    bar: 'bg-sand-300',
    dot: 'bg-sand-300',
  },
}

function tone(name?: Tone) {
  return TONE[name ?? 'blue']
}

export function ActorNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div className="flex h-full w-full flex-col items-center justify-center gap-1.5 text-center">
      <Ports />
      {/* Saída das associações no centro do ícone, não na borda do nó. */}
      <Handle
        id="actor"
        type="source"
        position={Position.Right}
        className="!h-1.5 !w-1.5 !min-h-0 !min-w-0 !border-0 !bg-transparent !opacity-0"
        style={{ top: 37, left: 'calc(50% + 24px)', right: 'auto', transform: 'translate(0, -50%)' }}
      />
      <div
        className={`flex h-12 w-12 items-center justify-center rounded-full border-2 ${t.box} ${t.border} ${t.text}`}
      >
        <UserRound className="h-6 w-6" strokeWidth={1.8} />
      </div>
      <span className="text-sm font-semibold text-ink-800">{data.label}</span>
    </div>
  )
}

export function UseCaseNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div
      className={`flex h-full w-full items-center gap-2.5 rounded-full border px-3 text-center text-[13px] font-medium leading-tight ${t.box} ${t.border} ${t.text}`}
    >
      <Ports />
      {data.badge ? (
        <span className="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border border-line bg-surface/80 px-1 text-[9px] font-bold tabular-nums opacity-80">
          {data.badge}
        </span>
      ) : null}
      <span className="flex-1 truncate px-1">{data.label}</span>
    </div>
  )
}

export function ClassNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div className="flex h-full w-full flex-col overflow-hidden rounded-lg border border-sand-300 bg-surface shadow-sm">
      <Ports />
      <div className={`border-b px-3 py-1.5 text-center text-[12px] font-bold ${t.header} ${t.border}`}>
        {data.label}
      </div>
      <div className="flex-1 px-2.5 py-1.5 font-mono text-[10px] leading-[1.55] text-ink-700">
        {(data.fields ?? []).map((f) => (
          <div key={f.name} className="flex justify-between gap-2">
            <span className="truncate">{f.name}</span>
            <span className="shrink-0 text-ink-500">{f.type}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

export function EntityNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div className="flex h-full w-full flex-col overflow-hidden rounded-lg border border-sand-300 bg-surface shadow-sm">
      <Ports />
      <div className={`flex items-center justify-between border-b px-2.5 py-1 ${t.header} ${t.border}`}>
        <span className="font-mono text-[11px] font-bold">{data.label}</span>
        {data.meta ? <span className="text-[9px] opacity-70">{data.meta}</span> : null}
      </div>
      <div className="flex-1 px-2 py-1 font-mono text-[9.5px] leading-[1.6] text-ink-700">
        {(data.fields ?? []).map((f) => (
          <div key={f.name} className="flex items-center justify-between gap-1">
            <span className="flex items-center gap-1 truncate">
              {f.key ? (
                <span
                  className={`inline-flex h-3.5 min-w-3.5 items-center justify-center rounded px-0.5 text-[8px] font-bold text-white ${
                    f.key === 'PK' ? 'bg-wine-600' : 'bg-slate-500'
                  }`}
                >
                  {f.key}
                </span>
              ) : null}
              <span className="truncate">{f.name}</span>
            </span>
            <span className="shrink-0 text-ink-500">{f.type}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

const ICONS: Record<string, typeof Server> = {
  monitor: Monitor,
  server: Server,
  database: Database,
  boxes: Boxes,
  workflow: Workflow,
  dot: CircleDot,
}

export function ComponentNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  const Icon = ICONS[data.icon ?? 'boxes'] ?? Boxes
  return (
    <div
      className={`flex h-full w-full items-center gap-2.5 rounded-lg border px-3 py-2 ${t.box} ${t.border}`}
    >
      <Ports />
      <Icon className={`h-5 w-5 shrink-0 ${t.text}`} strokeWidth={1.7} />
      <div className="min-w-0">
        <div className={`text-[11.5px] font-bold leading-tight ${t.text}`}>{data.label}</div>
        {data.subtitle ? (
          <div className="mt-0.5 text-[9.5px] leading-tight text-ink-500">{data.subtitle}</div>
        ) : null}
      </div>
    </div>
  )
}

export function StateNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div
      className={`flex h-full w-full items-center gap-2 rounded-md border border-sand-300 bg-surface px-2.5 shadow-sm`}
    >
      <Ports />
      <span className={`h-full w-1.5 rounded-full ${t.bar}`} />
      <div className="min-w-0">
        <div className="text-[11.5px] font-semibold leading-tight text-ink-800">{data.label}</div>
        {data.meta ? <div className="font-mono text-[9px] text-ink-500">{data.meta}</div> : null}
      </div>
    </div>
  )
}

export function LifelineNode({ data }: NodeProps<DiagramNode>) {
  const t = tone(data.tone)
  return (
    <div className="relative h-full w-full">
      <Ports />
      <div
        className={`relative z-10 mx-auto w-fit rounded-md border px-2 py-1 text-center text-[11px] font-bold shadow-sm ${t.box} ${t.border} ${t.text}`}
      >
        {data.label}
      </div>
      <svg
        className="pointer-events-none absolute inset-0 h-full w-full"
        aria-hidden="true"
      >
        <line
          x1="50%"
          y1="38"
          x2="50%"
          y2="100%"
          stroke="rgb(var(--ink-700))"
          strokeOpacity="0.6"
          strokeWidth="1.8"
          strokeDasharray="7 6"
        />
      </svg>
    </div>
  )
}

export function LayerNode({ data }: NodeProps<DiagramNode>) {
  return (
    <div className="pointer-events-none h-full w-full rounded-xl border border-dashed border-sand-300 bg-sand-50/40">
      <div className="absolute -top-2.5 left-3 rounded bg-surface px-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-500">
        {data.label}
      </div>
    </div>
  )
}

export function NoteNode({ data }: NodeProps<DiagramNode>) {
  return (
    <div className="h-full w-full rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] leading-tight text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
      {data.label}
    </div>
  )
}

export const nodeTypes = {
  actor: ActorNode,
  usecase: UseCaseNode,
  class: ClassNode,
  entity: EntityNode,
  component: ComponentNode,
  state: StateNode,
  lifeline: LifelineNode,
  layer: LayerNode,
  note: NoteNode,
}
