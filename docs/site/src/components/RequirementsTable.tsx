import type { Requirement } from '@/lib/types'

const PRIORIDADE: Record<string, string> = {
  Essencial: 'bg-wine-600 text-white',
  Importante: 'bg-amber-600 text-white',
  Desejável: 'bg-emerald-600 text-white',
}

export function RequirementsTable({
  items,
  tipo,
}: {
  items: Requirement[]
  tipo: 'RF' | 'RNF'
}) {
  if (!items.length) return null

  return (
    <div className="my-6">
      <div className="mb-2 flex items-center justify-between">
        <span className="text-[11px] font-bold uppercase tracking-wider text-ink-500">
          {tipo === 'RF' ? 'Requisitos funcionais' : 'Requisitos não funcionais'}
        </span>
        <span className="badge">{items.length} itens</span>
      </div>

      <div className="hidden overflow-hidden rounded-xl border border-sand-200 shadow-card md:block">
        <table className="w-full border-collapse text-[13px]">
          <thead>
            <tr className="bg-wine-600 text-left text-white">
              <th className="w-16 border-b border-wine-700 px-3 py-2 font-semibold">ID</th>
              <th className="w-52 border-b border-wine-700 px-3 py-2 font-semibold">Requisito</th>
              <th className="w-28 border-b border-wine-700 px-3 py-2 font-semibold">Prioridade</th>
              <th className="border-b border-wine-700 px-3 py-2 font-semibold">Enunciado</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item, i) => (
              <tr key={item.id} className={i % 2 === 1 ? 'bg-sand-100' : 'bg-surface'}>
                <td className="border-b border-sand-200 px-3 py-2.5 align-top">
                  <span className="font-mono text-[11px] font-bold text-wine-700">{item.id}</span>
                </td>
                <td className="border-b border-sand-200 px-3 py-2.5 align-top font-semibold text-ink-800">
                  {item.name}
                </td>
                <td className="border-b border-sand-200 px-3 py-2.5 align-top">
                  <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ${
                      PRIORIDADE[item.priority] ?? 'bg-slate-500 text-white'
                    }`}
                  >
                    {item.priority}
                  </span>
                </td>
                <td className="border-b border-sand-200 px-3 py-2.5 align-top leading-6 text-ink-700">
                  {item.description}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="space-y-3 md:hidden">
        {items.map((item) => (
          <div key={item.id} className="card p-4">
            <div className="flex items-center justify-between gap-2">
              <span className="font-mono text-[11px] font-bold text-wine-700">{item.id}</span>
              <span
                className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                  PRIORIDADE[item.priority] ?? 'bg-slate-500 text-white'
                }`}
              >
                {item.priority}
              </span>
            </div>
            <div className="mt-1 text-sm font-semibold text-ink-800">{item.name}</div>
            <p className="mt-1.5 text-[13px] leading-6 text-ink-700">{item.description}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
