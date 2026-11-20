import { Moon, Sun } from 'lucide-react'
import { useTheme } from '@/lib/useTheme'

export function ThemeToggle({ className = '' }: { className?: string }) {
  const { tema, alternar } = useTheme()
  const escuro = tema === 'dark'

  return (
    <button
      type="button"
      onClick={alternar}
      className={`btn-ghost !px-2.5 ${className}`}
      aria-label={escuro ? 'Ativar tema claro' : 'Ativar tema escuro'}
      title={escuro ? 'Tema claro' : 'Tema escuro'}
    >
      {escuro ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
    </button>
  )
}
