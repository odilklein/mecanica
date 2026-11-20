import ReactMarkdown, { type Components } from 'react-markdown'
import remarkGfm from 'remark-gfm'
import rehypeSlug from 'rehype-slug'
import type { DocumentContent } from '@/lib/types'
import { DiagramFigure } from './DiagramFigure'
import { RequirementsTable } from './RequirementsTable'

const components: Components = {
  img: ({ src, alt }) => {
    if (!src) return null
    if (src.includes('diagramas/png/')) {
      return <DiagramFigure src={src} alt={alt ?? ''} />
    }
    return (
      <figure className="my-6">
        <img
          src={src}
          alt={alt ?? ''}
          loading="lazy"
          className="mx-auto w-full max-w-3xl rounded-lg border border-sand-200 shadow-sm"
        />
        {alt ? (
          <figcaption className="mt-2 text-center text-xs italic text-ink-500">{alt}</figcaption>
        ) : null}
      </figure>
    )
  },
  table: ({ children }) => (
    <div className="my-5 overflow-x-auto rounded-lg border border-sand-200">
      <table>{children}</table>
    </div>
  ),
  a: ({ href, children }) => {
    const externo = href?.startsWith('http')
    return (
      <a
        href={href}
        target={externo ? '_blank' : undefined}
        rel={externo ? 'noreferrer' : undefined}
      >
        {children}
      </a>
    )
  },
}

export function MarkdownDocument({ content }: { content: DocumentContent }) {
  const partes = content.markdown.split(/(\[\[RF\]\]|\[\[RNF\]\])/)

  return (
    <div className="doc-content">
      {partes.map((parte, indice) => {
        if (parte === '[[RF]]') {
          return (
            <RequirementsTable
              key="rf"
              tipo="RF"
              items={content.requirements?.rf ?? []}
            />
          )
        }
        if (parte === '[[RNF]]') {
          return (
            <RequirementsTable
              key="rnf"
              tipo="RNF"
              items={content.requirements?.rnf ?? []}
            />
          )
        }
        if (!parte.trim()) return null
        return (
          <ReactMarkdown
            key={indice}
            remarkPlugins={[remarkGfm]}
            rehypePlugins={[rehypeSlug]}
            components={components}
          >
            {parte}
          </ReactMarkdown>
        )
      })}
    </div>
  )
}
