import { Suspense, lazy, useEffect, useRef, useState } from 'react'
import type { DiagramDefinition } from '@/diagrams/types'
import { DiagramMiniature } from './DiagramMiniature'

const DiagramPreview = lazy(() =>
  import('./DiagramPreview').then((m) => ({ default: m.DiagramPreview })),
)

/**
 * Só monta o React Flow quando o card entra na viewport. Até lá (e enquanto o
 * chunk carrega), mostra a miniatura SVG — sem piscar em branco.
 */
export function DiagramPreviewLazy({
  diagram,
  className = '',
}: {
  diagram: DiagramDefinition
  className?: string
}) {
  const ref = useRef<HTMLDivElement>(null)
  const [visivel, setVisivel] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (typeof IntersectionObserver === 'undefined') {
      setVisivel(true)
      return
    }
    const observador = new IntersectionObserver(
      ([entrada]) => {
        if (entrada.isIntersecting) {
          setVisivel(true)
          observador.disconnect()
        }
      },
      { rootMargin: '260px 0px' },
    )
    observador.observe(el)
    return () => observador.disconnect()
  }, [])

  return (
    <div ref={ref} className={`relative ${className}`}>
      {visivel ? (
        <Suspense fallback={<DiagramMiniature diagram={diagram} className="h-full w-full" />}>
          <DiagramPreview diagram={diagram} />
        </Suspense>
      ) : (
        <DiagramMiniature diagram={diagram} className="h-full w-full" />
      )}
    </div>
  )
}
