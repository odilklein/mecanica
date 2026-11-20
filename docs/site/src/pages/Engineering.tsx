import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, BookOpen, GitBranch } from 'lucide-react'
import { adrDocs, engenhariaDocs } from '@/content/generated'
import { MarkdownDocument } from '@/components/MarkdownDocument'
import { Toc } from '@/components/Toc'

export function Engineering() {
  const { slug } = useParams<{ slug?: string }>()
  const todos = [...engenhariaDocs, ...adrDocs]
  const doc = todos.find((d) => d.slug === slug) ?? engenhariaDocs[0]

  return (
    <div className="container-page py-8 lg:py-10">
      <div className="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="text-[11px] font-bold uppercase tracking-[0.18em] text-wine-700">
            Engenharia
          </div>
          <div className="mt-1 text-sm text-ink-500">
            Conteúdo técnico retirado do código e do banco em execução.
          </div>
        </div>
        <Link to="/documento" className="btn-ghost">
          <BookOpen className="h-4 w-4" /> Documento oficial
        </Link>
      </div>

      <details className="no-print mb-6 rounded-xl border border-sand-200 bg-surface p-4 lg:hidden">
        <summary className="cursor-pointer text-sm font-semibold text-ink-800">Documentos</summary>
        <NavDocs atual={doc.slug} />
      </details>

      <div className="grid gap-10 lg:grid-cols-[260px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,860px)]">
        <aside className="no-print hidden lg:block">
          <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-auto pr-2">
            <NavDocs atual={doc.slug} />
            <div className="mt-6 border-t border-sand-200 pt-4">
              <Toc items={doc.toc} />
            </div>
          </div>
        </aside>

        <article className="print-full min-w-0">
          <div className="mb-6">
            <div className="badge">
              {doc.slug.startsWith('adr-') ? (
                <>
                  <GitBranch className="h-3.5 w-3.5" /> ADR
                </>
              ) : (
                'Documento de engenharia'
              )}
            </div>
            <h1 className="mt-3 border-b-2 border-wine-500 pb-2 text-3xl font-bold tracking-tight text-wine-700">
              {doc.title}
            </h1>
            <p className="mt-2 text-sm text-ink-500">
              {doc.toc.length} seções · conteúdo derivado de `docs/engenharia`
            </p>
          </div>
          <MarkdownDocument content={doc} />
          <div className="no-print mt-10 border-t border-sand-200 pt-4">
            <Link to="/documento" className="inline-flex items-center gap-1.5 text-sm font-semibold text-wine-700 hover:underline">
              <ArrowLeft className="h-4 w-4" /> Voltar ao documento oficial
            </Link>
          </div>
        </article>
      </div>
    </div>
  )
}

function NavDocs({ atual }: { atual: string }) {
  return (
    <nav className="text-sm" aria-label="Documentos de engenharia">
      <div className="mb-2 text-[11px] font-bold uppercase tracking-[0.14em] text-ink-500">
        Engenharia
      </div>
      <ul className="space-y-0.5">
        {engenhariaDocs.map((d) => (
          <li key={d.slug}>
            <Link
              to={`/engenharia/${d.slug}`}
              className={`block rounded-md px-2.5 py-1.5 transition ${
                d.slug === atual
                  ? 'bg-wine-50 font-semibold text-wine-700'
                  : 'text-ink-500 hover:bg-sand-100 hover:text-ink-800'
              }`}
            >
              {d.title}
            </Link>
          </li>
        ))}
      </ul>
      <div className="mb-2 mt-5 text-[11px] font-bold uppercase tracking-[0.14em] text-ink-500">
        Decisões (ADRs)
      </div>
      <ul className="space-y-0.5">
        {adrDocs.map((d) => (
          <li key={d.slug}>
            <Link
              to={`/engenharia/${d.slug}`}
              className={`block rounded-md px-2.5 py-1.5 text-[13px] transition ${
                d.slug === atual
                  ? 'bg-wine-50 font-semibold text-wine-700'
                  : 'text-ink-500 hover:bg-sand-100 hover:text-ink-800'
              }`}
            >
              {d.title.replace(/^ADR-\d+\s*—\s*/, '')}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  )
}
