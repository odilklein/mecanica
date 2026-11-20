import { Link } from 'react-router-dom'
import { BookOpen, Home } from 'lucide-react'

export function NotFound() {
  return (
    <div className="container-page flex min-h-[60vh] flex-col items-center justify-center py-16 text-center">
      <div className="text-6xl font-bold text-wine-200">404</div>
      <h1 className="mt-2 text-2xl font-bold text-ink-900">Página não encontrada</h1>
      <p className="mt-2 max-w-md text-sm text-ink-500">
        O endereço acessado não existe ou o diagrama foi movido. Volte à visão geral ou abra o
        documento oficial.
      </p>
      <div className="mt-6 flex gap-2">
        <Link to="/" className="btn-primary">
          <Home className="h-4 w-4" /> Visão geral
        </Link>
        <Link to="/documento" className="btn-ghost">
          <BookOpen className="h-4 w-4" /> Documento
        </Link>
      </div>
    </div>
  )
}
