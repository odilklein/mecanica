import { Suspense, lazy, useState } from 'react'
import { ArrowRight, Maximize2 } from 'lucide-react'
import { getDiagram } from '@/diagrams'
import { DiagramPreviewLazy } from './DiagramPreviewLazy'

const DiagramModal = lazy(() =>
  import('./DiagramModal').then((m) => ({ default: m.DiagramModal })),
)

export function DiagramFigure({ src, alt }: { src: string; alt: string }) {
  const arquivo = src.split('/').pop()?.replace('.png', '') ?? ''
  const diagram = getDiagram(arquivo)
  const [aberto, setAberto] = useState(false)

  if (!diagram) {
    return (
      <figure className="my-6">
        <img src={src} alt={alt} loading="lazy" className="mx-auto rounded-lg border border-line" />
        {alt ? (
          <figcaption className="mt-2 text-center text-xs italic text-ink-500">{alt}</figcaption>
        ) : null}
      </figure>
    )
  }

  return (
    <div id={`figura-${diagram.figure}`} className="my-8 scroll-mt-24">
      <div
        role="button"
        tabIndex={0}
        onClick={() => setAberto(true)}
        onKeyDown={(evento) => {
          if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault()
            setAberto(true)
          }
        }}
        aria-label={`Abrir ${diagram.title} em tela cheia`}
        className="card group block w-full cursor-pointer overflow-hidden text-left transition hover:-translate-y-0.5 hover:border-wine-300 hover:shadow-lift"
      >
        <span className="flex items-center justify-between border-b border-line bg-surface px-4 py-2">
          <span className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-wine-700">
            <Maximize2 className="h-3.5 w-3.5" />
            Figura {diagram.figure}
          </span>
          <span className="text-[10px] font-semibold uppercase tracking-wider text-ink-500">
            {diagram.category} · clique para ampliar
          </span>
        </span>

        <span className="block aspect-[16/9] bg-sand-50 p-3 sm:p-5">
          <DiagramPreviewLazy diagram={diagram} className="h-full w-full" />
        </span>

        <span className="flex items-center justify-between gap-4 border-t border-line bg-surface px-4 py-3">
          <span className="min-w-0">
            <span className="block truncate text-sm font-bold text-ink-800">{diagram.title}</span>
            <span className="block truncate text-xs text-ink-500">{diagram.short}</span>
          </span>
          <span className="btn-primary shrink-0 !px-3 !py-1.5 !text-xs">
            Abrir diagrama
            <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
          </span>
        </span>
      </div>
      {alt ? <p className="mt-2 text-center text-xs italic text-ink-500">{alt}</p> : null}

      {aberto ? (
        <Suspense fallback={null}>
          <DiagramModal diagram={diagram} open={aberto} onClose={() => setAberto(false)} />
        </Suspense>
      ) : null}
    </div>
  )
}
