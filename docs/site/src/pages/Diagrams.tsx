import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Search, Shapes } from 'lucide-react'
import { diagrams } from '@/diagrams'
import type { DiagramCategory } from '@/diagrams/types'
import { DiagramPreviewLazy } from '@/components/DiagramPreviewLazy'

const CATEGORIAS: ('Todos' | DiagramCategory)[] = [
  'Todos',
  'UML',
  'Banco de dados',
  'Arquitetura',
  'Fluxo',
]

export function Diagrams() {
  const [categoria, setCategoria] = useState<(typeof CATEGORIAS)[number]>('Todos')
  const [busca, setBusca] = useState('')

  const filtrados = useMemo(() => {
    const termo = busca.trim().toLowerCase()
    return diagrams.filter((d) => {
      const okCategoria = categoria === 'Todos' || d.category === categoria
      const okBusca =
        !termo ||
        d.title.toLowerCase().includes(termo) ||
        d.short.toLowerCase().includes(termo) ||
        d.description.toLowerCase().includes(termo)
      return okCategoria && okBusca
    })
  }, [categoria, busca])

  return (
    <div className="container-page py-8 lg:py-10">
      <div className="max-w-2xl">
        <div className="text-[11px] font-bold uppercase tracking-[0.18em] text-wine-700">
          Diagramas
        </div>
        <h1 className="mt-2 text-3xl font-bold tracking-tight text-ink-900 sm:text-4xl">
          Todos os diagramas, interativos
        </h1>
        <p className="mt-3 text-sm leading-6 text-ink-600">
          Os 14 diagramas do documento foram recriados em React Flow. Arraste os nós, use o
          zoom e o minimapa, passe o mouse para ver os detalhes de cada componente e ligue as
          animações no botão “Animar”.
        </p>
      </div>

      <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap gap-1.5">
          {CATEGORIAS.map((c) => (
            <button
              key={c}
              type="button"
              onClick={() => setCategoria(c)}
              className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                categoria === c
                  ? 'border-wine-600 bg-wine-600 text-white'
                  : 'border-sand-300 bg-surface text-ink-600 hover:border-wine-200 hover:text-wine-700'
              }`}
            >
              {c}
            </button>
          ))}
        </div>

        <label className="relative block w-full sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
          <input
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar diagrama…"
            className="w-full rounded-lg border border-sand-300 bg-surface py-2 pl-9 pr-3 text-sm text-ink-800 placeholder:text-ink-500 focus:border-wine-300 focus:outline-none focus:ring-2 focus:ring-wine-100"
          />
        </label>
      </div>

      {filtrados.length === 0 ? (
        <div className="mt-10 rounded-xl border border-dashed border-sand-300 bg-surface p-10 text-center text-sm text-ink-500">
          Nenhum diagrama encontrado para “{busca}”.
        </div>
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtrados.map((d) => (
            <Link
              key={d.id}
              to={`/diagramas/${d.id}`}
              className="card group overflow-hidden transition hover:-translate-y-0.5 hover:border-wine-200 hover:shadow-lift"
            >
              <div className="flex items-center justify-between border-b border-sand-200 px-4 py-2">
                <span className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-wine-700">
                  <Shapes className="h-3.5 w-3.5" /> Figura {d.figure}
                </span>
                <span className="text-[10px] font-semibold uppercase tracking-wider text-ink-500">
                  {d.category}
                </span>
              </div>
              <div className="aspect-[16/10] bg-sand-50 p-3">
                <DiagramPreviewLazy diagram={d} className="h-full w-full" />
              </div>
              <div className="border-t border-sand-200 px-4 py-3">
                <div className="truncate text-sm font-bold text-ink-800">{d.title}</div>
                <div className="mt-0.5 flex items-center justify-between gap-2">
                  <span className="truncate text-xs text-ink-500">{d.short}</span>
                  <ArrowRight className="h-3.5 w-3.5 shrink-0 text-wine-600 transition group-hover:translate-x-0.5" />
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
