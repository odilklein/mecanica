import { lazy, Suspense, useEffect } from 'react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { Layout } from '@/components/Layout'
import { Home } from '@/pages/Home'
import { Document } from '@/pages/Document'
import { Diagrams } from '@/pages/Diagrams'
const DiagramPage = lazy(() =>
  import('@/pages/DiagramPage').then((m) => ({ default: m.DiagramPage })),
)
import { Engineering } from '@/pages/Engineering'
import { NotFound } from '@/pages/NotFound'

function ScrollToTop() {
  const { pathname, hash } = useLocation()
  useEffect(() => {
    if (hash) {
      const el = document.getElementById(hash.slice(1))
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
        return
      }
    }
    window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior })
  }, [pathname, hash])
  return null
}

export default function App() {
  return (
    <Layout>
      <ScrollToTop />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/documento" element={<Document />} />
        <Route path="/diagramas" element={<Diagrams />} />
        <Route
          path="/diagramas/:id"
          element={
            <Suspense
              fallback={
                <div className="flex min-h-[60vh] items-center justify-center text-sm text-ink-500">
                  Carregando diagrama…
                </div>
              }
            >
              <DiagramPage />
            </Suspense>
          }
        />
        <Route path="/engenharia" element={<Engineering />} />
        <Route path="/engenharia/:slug" element={<Engineering />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
    </Layout>
  )
}
