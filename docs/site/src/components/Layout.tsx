import type { ReactNode } from 'react'
import { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { ThemeToggle } from './ThemeToggle'
import { SITE_HOST } from '@/lib/site'
import {
  BookOpen,
  Download,
  Github,
  Menu,
  Network,
  Wrench,
  X,
} from 'lucide-react'

const NAV = [
  { to: '/', label: 'Visão geral', end: true },
  { to: '/documento', label: 'Documento' },
  { to: '/diagramas', label: 'Diagramas' },
  { to: '/engenharia', label: 'Engenharia', end: true },
  { to: '/engenharia/07-seguranca-ofensiva', label: 'Segurança' },
]

function Brand() {
  return (
    <Link to="/" className="flex items-center gap-2.5">
      <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-wine-600 text-white shadow-sm">
        <Wrench className="h-5 w-5" strokeWidth={2} />
      </span>
      <span className="leading-tight">
        <span className="block text-[15px] font-bold tracking-tight text-ink-900">Torque</span>
        <span className="block text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-500">
          Documentação
        </span>
      </span>
    </Link>
  )
}

export function Layout({ children }: { children: ReactNode }) {
  const [aberto, setAberto] = useState(false)

  return (
    <div className="flex min-h-screen flex-col">
      <header className="no-print sticky top-0 z-40 border-b border-sand-200/80 bg-sand-50/85 backdrop-blur">
        <div className="container-page flex h-16 items-center justify-between gap-4">
          <Brand />

          <nav className="hidden items-center gap-1 lg:flex">
            {NAV.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `rounded-lg px-3 py-2 text-sm font-semibold transition ${
                    isActive
                      ? 'bg-wine-50 text-wine-700'
                      : 'text-ink-500 hover:bg-sand-100 hover:text-ink-800'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="hidden items-center gap-2 lg:flex">
            <ThemeToggle />
            <a
              href="downloads/PI-ES2-2026.pdf"
              download
              className="btn-primary"
            >
              <Download className="h-4 w-4" />
              PDF
            </a>
            <a
              href="downloads/PI-ES2-2026.docx"
              download
              className="btn-ghost"
            >
              DOCX
            </a>
            <a
              href="https://github.com/odilklein/mecanica"
              target="_blank"
              rel="noreferrer"
              className="btn-ghost !px-2.5"
              aria-label="Repositório no GitHub"
            >
              <Github className="h-4 w-4" />
            </a>
          </div>

          <button
            type="button"
            onClick={() => setAberto((v) => !v)}
            className="btn-ghost !px-2.5 lg:hidden"
            aria-label={aberto ? 'Fechar menu' : 'Abrir menu'}
            aria-expanded={aberto}
          >
            {aberto ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>

        {aberto ? (
          <div className="border-t border-sand-200 bg-sand-50 lg:hidden">
            <div className="container-page flex flex-col gap-1 py-3">
              {NAV.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  onClick={() => setAberto(false)}
                  className={({ isActive }) =>
                    `flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold ${
                      isActive ? 'bg-wine-50 text-wine-700' : 'text-ink-600'
                    }`
                  }
                >
                  <Network className="h-4 w-4 opacity-60" />
                  {item.label}
                </NavLink>
              ))}
              <div className="mt-2 flex gap-2">
                <ThemeToggle className="flex-none" />
                <a href="downloads/PI-ES2-2026.pdf" download className="btn-primary flex-1">
                  <Download className="h-4 w-4" /> PDF
                </a>
                <a href="downloads/PI-ES2-2026.docx" download className="btn-ghost flex-1">
                  DOCX
                </a>
              </div>
            </div>
          </div>
        ) : null}
      </header>

      <main className="flex-1">{children}</main>

      <footer className="no-print mt-16 border-t border-sand-200 bg-surface">
        <div className="container-page grid gap-8 py-10 md:grid-cols-[1.4fr_1fr_1fr]">
          <div>
            <Brand />
            <p className="mt-3 max-w-sm text-sm leading-6 text-ink-500">
              Documentação oficial do Torque, sistema de gestão para oficinas mecânicas
              desenvolvido como Projeto Integrador do curso de DSM da FATEC Araras.
            </p>
          </div>
          <div>
            <h3 className="text-xs font-bold uppercase tracking-wider text-ink-500">Navegar</h3>
            <ul className="mt-3 space-y-2 text-sm">
              {NAV.map((item) => (
                <li key={item.to}>
                  <Link to={item.to} className="text-ink-600 hover:text-wine-700">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
          <div>
            <h3 className="text-xs font-bold uppercase tracking-wider text-ink-500">Projeto</h3>
            <ul className="mt-3 space-y-2 text-sm text-ink-600">
              <li className="flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-wine-500" />
                Team Zenitec · Auto Mecânica Leme
              </li>
              <li>FATEC Araras “Antônio Brambilla”</li>
              <li>Apresentação: 24/11/2026</li>
              <li>
                <a
                  href="https://github.com/odilklein/mecanica"
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 text-wine-700 hover:underline"
                >
                  <Github className="h-4 w-4" /> odilklein/mecanica
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="border-t border-sand-200 py-4">
          <div className="container-page flex flex-col items-center justify-between gap-2 text-xs text-ink-500 sm:flex-row">
            <span>© 2026 Team Zenitec. Documento acadêmico.</span>
            <span className="flex items-center gap-2">
              <span className="font-mono text-[11px]">{SITE_HOST}</span>
              <span aria-hidden="true">·</span>
              <span>React · Tailwind CSS · React Flow</span>
            </span>
          </div>
        </div>
      </footer>
    </div>
  )
}
