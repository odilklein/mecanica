import type { TocItem } from '@/lib/types'
import { useActiveHeading } from '@/lib/useActiveHeading'

export function Toc({ items, className = '' }: { items: TocItem[]; className?: string }) {
  const visiveis = items.filter((i) => i.level <= 2)
  const ativo = useActiveHeading(visiveis.map((i) => i.id))

  return (
    <nav className={`text-sm ${className}`} aria-label="Sumário">
      <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-ink-500">
        Nesta página
      </div>
      <ul className="space-y-0.5 border-l border-sand-200">
        {visiveis.map((item) => {
          const ativoItem = item.id === ativo
          return (
            <li key={item.id}>
              <a
                href={`#${item.id}`}
                className={`-ml-px block border-l-2 py-1.5 pr-2 transition ${
                  item.level === 2 ? 'pl-6 text-[13px]' : 'pl-3 font-semibold'
                } ${
                  ativoItem
                    ? 'border-wine-500 text-wine-700'
                    : 'border-transparent text-ink-500 hover:border-wine-200 hover:text-ink-800'
                }`}
              >
                {item.text}
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
