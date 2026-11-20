import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router-dom'
import { ExternalLink, Maximize2, Minimize2, MousePointer2, X } from 'lucide-react'
import type { DiagramDefinition } from '@/diagrams/types'
import { DiagramCanvas } from './DiagramCanvas'
import { useFullscreen } from '@/lib/useFullscreen'

export function DiagramModal({
  diagram,
  open,
  onClose,
}: {
  diagram: DiagramDefinition
  open: boolean
  onClose: () => void
}) {
  const painelRef = useRef<HTMLDivElement>(null)
  const fecharRef = useRef<HTMLButtonElement>(null)
  const { telaCheia, alternar } = useFullscreen(painelRef)

  useEffect(() => {
    if (!open) return
    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key === 'Escape') onClose()
    }
    const overflowAnterior = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', aoTeclar)
    window.setTimeout(() => fecharRef.current?.focus(), 30)
    return () => {
      document.body.style.overflow = overflowAnterior
      document.removeEventListener('keydown', aoTeclar)
    }
  }, [open, onClose])

  if (!open) return null

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-label={diagram.title}
      onMouseDown={(evento) => {
        if (evento.target === evento.currentTarget) onClose()
      }}
    >
      <div
        ref={painelRef}
        className="diagram-modal-panel animate-fade-up flex h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-2xl"
      >
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="badge border-wine-200 bg-wine-50 text-wine-700">
                Figura {diagram.figure}
              </span>
              <span className="badge">{diagram.category}</span>
              <span className="badge">interativo</span>
            </div>
            <h2 className="mt-1.5 truncate text-base font-bold text-ink-900 sm:text-lg">
              {diagram.title}
            </h2>
          </div>

          <div className="flex items-center gap-1.5">
            <button
              type="button"
              onClick={alternar}
              className="btn-ghost !px-2.5"
              aria-label={telaCheia ? 'Sair da tela cheia' : 'Tela cheia'}
              title={telaCheia ? 'Sair da tela cheia' : 'Tela cheia'}
            >
              {telaCheia ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
            </button>
            <Link
              to={`/diagramas/${diagram.id}`}
              onClick={onClose}
              className="btn-ghost !px-2.5"
              aria-label="Abrir página completa do diagrama"
              title="Abrir página completa"
            >
              <ExternalLink className="h-4 w-4" />
            </Link>
            <button
              ref={fecharRef}
              type="button"
              onClick={onClose}
              className="btn-ghost !px-2.5"
              aria-label="Fechar"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </header>

        <div className="relative min-h-0 flex-1 bg-[var(--flow-canvas)]">
          <DiagramCanvas
            diagram={diagram}
            height="100%"
            className="h-full overflow-hidden"
          />
        </div>

        <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-2.5 text-[11px] text-ink-500 sm:px-5">
          <span className="inline-flex items-center gap-1.5">
            <MousePointer2 className="h-3.5 w-3.5" />
            Arraste os nós, use o zoom ou a tela cheia. Esc fecha.
          </span>
          <Link
            to={`/diagramas/${diagram.id}`}
            onClick={onClose}
            className="font-semibold text-wine-700 hover:underline"
          >
            Abrir página completa
          </Link>
        </footer>
      </div>
    </div>,
    document.body,
  )
}
