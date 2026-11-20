import { Download, Printer, Users } from 'lucide-react'
import { identificacao, documento } from '@/content/generated'
import { MarkdownDocument } from '@/components/MarkdownDocument'
import { Toc } from '@/components/Toc'

function Capa() {
  return (
    <section className="mb-10 overflow-hidden rounded-2xl border border-sand-200 bg-surface shadow-card">
      <div className="relative overflow-hidden bg-[#5E170A] px-6 py-9 text-[#F7E4E0] sm:px-9">
        <div
          className="pointer-events-none absolute inset-0 opacity-30"
          style={{
            background:
              'radial-gradient(600px 220px at 85% 10%, rgba(255,255,255,0.35), transparent 60%)',
          }}
        />
        <div className="relative">
          <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#F3D9D0]">
            {identificacao.instituicao} · {identificacao.ano}
          </div>
          <h1 className="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
            {identificacao.sistema}
          </h1>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-[#F3D9D0]">
            Documentação oficial do Projeto Integrador, organizada no template da FATEC.
          </p>
          <div className="mt-5 flex flex-wrap gap-2 text-[11px] font-semibold">
            <span className="rounded-full bg-white/15 px-3 py-1">{identificacao.curso}</span>
            <span className="rounded-full bg-white/15 px-3 py-1">{identificacao.disciplina}</span>
            <span className="rounded-full bg-white/15 px-3 py-1">{identificacao.semestre}</span>
          </div>
        </div>
      </div>

      <div className="grid gap-6 p-6 sm:p-9 md:grid-cols-2">
        <dl className="space-y-3 text-sm">
          <div>
            <dt className="text-[11px] font-bold uppercase tracking-wider text-ink-500">Empresa</dt>
            <dd className="mt-0.5 font-semibold text-ink-800">{identificacao.grupo}</dd>
          </div>
          <div>
            <dt className="text-[11px] font-bold uppercase tracking-wider text-ink-500">Cliente</dt>
            <dd className="mt-0.5 font-semibold text-ink-800">Auto Mecânica Leme</dd>
          </div>
          <div>
            <dt className="text-[11px] font-bold uppercase tracking-wider text-ink-500">Professor</dt>
            <dd className="mt-0.5 font-semibold text-ink-800">{identificacao.professor}</dd>
          </div>
          <div>
            <dt className="text-[11px] font-bold uppercase tracking-wider text-ink-500">Apresentação</dt>
            <dd className="mt-0.5 font-semibold text-ink-800">{identificacao.data}</dd>
          </div>
        </dl>

        <div>
          <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-ink-500">
            <Users className="h-3.5 w-3.5" /> Integrantes
          </div>
          <ul className="mt-2 divide-y divide-sand-200 rounded-lg border border-sand-200">
            {identificacao.integrantes.map((p) => (
              <li key={p.nome} className="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                <span className="font-semibold text-ink-800">{p.nome}</span>
                <span className="shrink-0 text-right text-ink-500">{p.papel}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  )
}

export function Document() {
  return (
    <div className="container-page py-8 lg:py-10">
      <div className="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="text-[11px] font-bold uppercase tracking-[0.18em] text-wine-700">
            Documento oficial
          </div>
          <div className="mt-1 text-sm text-ink-500">
            {documento.toc.length} títulos · {documento.requirements?.rf.length ?? 0} requisitos funcionais ·{' '}
            {documento.requirements?.rnf.length ?? 0} não funcionais
          </div>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={() => window.print()} className="btn-ghost">
            <Printer className="h-4 w-4" /> Imprimir
          </button>
          <a href="downloads/PI-ES2-2026.pdf" download className="btn-primary">
            <Download className="h-4 w-4" /> PDF
          </a>
          <a href="downloads/PI-ES2-2026.docx" download className="btn-ghost">
            DOCX
          </a>
        </div>
      </div>

      <details className="no-print mb-6 rounded-xl border border-sand-200 bg-surface p-4 lg:hidden">
        <summary className="cursor-pointer text-sm font-semibold text-ink-800">Sumário</summary>
        <div className="mt-3">
          <Toc items={documento.toc} />
        </div>
      </details>

      <div className="grid gap-10 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[260px_minmax(0,860px)]">
        <aside className="no-print hidden lg:block">
          <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-auto pr-2">
            <Toc items={documento.toc} />
            <div className="mt-6 rounded-lg border border-sand-200 bg-surface p-3 text-[11px] leading-5 text-ink-500">
              As figuras abrem o diagrama interativo. Use o botão voltar do navegador para
              retornar ao ponto do documento.
            </div>
          </div>
        </aside>

        <article className="print-full min-w-0">
          <Capa />
          <MarkdownDocument content={documento} />
        </article>
      </div>
    </div>
  )
}
