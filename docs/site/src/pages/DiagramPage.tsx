import { useRef } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  ArrowLeft,
  ArrowRight,
  BookOpen,
  Hand,
  Maximize2,
  Minimize2,
  MousePointer2,
  ZoomIn,
} from 'lucide-react'
import { diagrams, getDiagram } from '@/diagrams'
import { DiagramCanvas } from '@/components/DiagramCanvas'
import { useFullscreen } from '@/lib/useFullscreen'
import { NotFound } from './NotFound'

export function DiagramPage() {
  const { id } = useParams<{ id: string }>()
  const diagram = id ? getDiagram(id) : undefined
  if (!diagram) return <NotFound />

  const canvasRef = useRef<HTMLDivElement>(null)
  const { telaCheia, alternar } = useFullscreen(canvasRef)
  const indice = diagrams.findIndex((d) => d.id === diagram.id)
  const anterior = indice > 0 ? diagrams[indice - 1] : undefined
  const proximo = indice < diagrams.length - 1 ? diagrams[indice + 1] : undefined
  const altura = Math.min(Math.max(diagram.height, 480), 700)

  return (
    <div className="container-page py-8 lg:py-10">
      <nav className="no-print mb-5 flex items-center gap-2 text-xs text-ink-500">
        <Link to="/diagramas" className="hover:text-wine-700">
          Diagramas
        </Link>
        <span>/</span>
        <span className="truncate text-ink-700">{diagram.short}</span>
      </nav>

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-3xl">
          <div className="flex flex-wrap items-center gap-2">
            <span className="badge border-wine-200 bg-wine-50 text-wine-700">
              Figura {diagram.figure}
            </span>
            <span className="badge">{diagram.category}</span>
            <span className="badge">
              <Maximize2 className="h-3 w-3" /> interativo
            </span>
          </div>
          <h1 className="mt-3 text-3xl font-bold tracking-tight text-ink-900">{diagram.title}</h1>
          <p className="mt-2 text-sm leading-6 text-ink-600">{diagram.description}</p>
        </div>

        <div className="no-print flex gap-2">
          <Link to={`/documento#figura-${diagram.figure}`} className="btn-ghost">
            <BookOpen className="h-4 w-4" /> No documento
          </Link>
          <Link to="/diagramas" className="btn-primary">
            <ArrowLeft className="h-4 w-4" /> Galeria
          </Link>
        </div>
      </div>

      <div ref={canvasRef} className="diagram-fullscreen relative mt-6">
        <DiagramCanvas key={diagram.id} diagram={diagram} height={altura} />
        <button
          type="button"
          onClick={alternar}
          className="no-print absolute right-3 top-3 z-10 inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface/95 px-2.5 py-1.5 text-xs font-semibold text-ink-700 shadow-sm backdrop-blur transition hover:border-wine-300 hover:text-wine-700"
        >
          {telaCheia ? <Minimize2 className="h-3.5 w-3.5" /> : <Maximize2 className="h-3.5 w-3.5" />}
          {telaCheia ? 'Sair' : 'Tela cheia'}
        </button>
      </div>

      <div className="no-print mt-5 grid gap-3 sm:grid-cols-3">
        <div className="card flex items-start gap-3 p-4">
          <Hand className="mt-0.5 h-5 w-5 shrink-0 text-wine-600" />
          <div>
            <div className="text-sm font-semibold text-ink-800">Arraste e explore</div>
            <p className="mt-0.5 text-xs leading-5 text-ink-500">
              Os nós podem ser movidos. Use o scroll para zoom e o minimapa para navegar.
            </p>
          </div>
        </div>
        <div className="card flex items-start gap-3 p-4">
          <ZoomIn className="mt-0.5 h-5 w-5 shrink-0 text-wine-600" />
          <div>
            <div className="text-sm font-semibold text-ink-800">Ajuste a visão</div>
            <p className="mt-0.5 text-xs leading-5 text-ink-500">
              Os controles no canto inferior esquerdo aproximam, afastam e reenquadram o diagrama.
            </p>
          </div>
        </div>
        <div className="card flex items-start gap-3 p-4">
          <MousePointer2 className="mt-0.5 h-5 w-5 shrink-0 text-wine-600" />
          <div>
            <div className="text-sm font-semibold text-ink-800">Volte ao texto</div>
            <p className="mt-0.5 text-xs leading-5 text-ink-500">
              “No documento” retorna à figura correspondente no capítulo.
            </p>
          </div>
        </div>
      </div>

      <div className="no-print mt-8 flex items-center justify-between gap-3 border-t border-sand-200 pt-5">
        {anterior ? (
          <Link
            to={`/diagramas/${anterior.id}`}
            className="group flex max-w-[45%] items-center gap-2 text-sm text-ink-600 hover:text-wine-700"
          >
            <ArrowLeft className="h-4 w-4 shrink-0 transition group-hover:-translate-x-0.5" />
            <span className="min-w-0">
              <span className="block text-[10px] font-bold uppercase tracking-wider text-ink-500">
                Anterior
              </span>
              <span className="block truncate font-semibold">{anterior.short}</span>
            </span>
          </Link>
        ) : (
          <span />
        )}
        {proximo ? (
          <Link
            to={`/diagramas/${proximo.id}`}
            className="group flex max-w-[45%] items-center gap-2 text-right text-sm text-ink-600 hover:text-wine-700"
          >
            <span className="min-w-0">
              <span className="block text-[10px] font-bold uppercase tracking-wider text-ink-500">
                Próximo
              </span>
              <span className="block truncate font-semibold">{proximo.short}</span>
            </span>
            <ArrowRight className="h-4 w-4 shrink-0 transition group-hover:translate-x-0.5" />
          </Link>
        ) : (
          <span />
        )}
      </div>
    </div>
  )
}
