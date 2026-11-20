import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  ArrowRight,
  ArrowUpRight,
  BookOpen,
  CheckCircle2,
  Download,
  FileText,
  Github,
  Layers,
  Network,
  ShieldCheck,
} from 'lucide-react'
import { documento, identificacao } from '@/content/generated'
import { diagrams } from '@/diagrams'
import { members } from '@/data/members'

const RECURSOS = [
  'Cadastro de clientes, veículos e funcionários.',
  'Ordens de serviço com diagnóstico, tarefas, equipe e histórico.',
  'Orçamentos com peças, mão de obra, desconto e impressão.',
  'Pedidos de peças vinculados à OS.',
  'Tickets internos, notificações e relatórios.',
  'Auditoria e assistente de IA com aprovação humana.',
]

function MemberCard({ member }: { member: (typeof members)[number] }) {
  const [erroFoto, setErroFoto] = useState(false)
  const mostrarFoto = Boolean(member.photo) && !erroFoto

  return (
    <article className="card flex flex-col p-5">
      <div className="flex items-center gap-4">
        {mostrarFoto ? (
          <img
            src={member.photo}
            alt={member.name}
            loading="lazy"
            onError={() => setErroFoto(true)}
            className="h-16 w-16 rounded-xl border border-line object-cover"
          />
        ) : (
          <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-[#B8270F] to-[#5E170A] text-lg font-bold text-white">
            {member.initials}
          </div>
        )}
        <div className="min-w-0">
          <h3 className="truncate text-sm font-bold text-ink-900">{member.short}</h3>
          <p className="mt-0.5 text-xs font-semibold text-wine-700">{member.role}</p>
        </div>
      </div>

      <p className="mt-4 text-[13px] leading-6 text-ink-700">{member.contribution}</p>

      <div className="mt-4 flex flex-wrap gap-1.5">
        {member.focus.map((foco) => (
          <span key={foco} className="badge">
            {foco}
          </span>
        ))}
      </div>

      <div className="mt-auto pt-4">
        <a
          href={member.github ?? 'https://github.com/odilklein/mecanica'}
          target="_blank"
          rel="noreferrer"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-700 hover:text-wine-700"
        >
          <Github className="h-4 w-4" />
          {member.github ? 'GitHub' : 'Repositório'}
          <ArrowUpRight className="h-3.5 w-3.5" />
        </a>
      </div>
    </article>
  )
}

export function Home() {
  return (
    <div className="container-page py-10 lg:py-14">
      <section className="grid items-center gap-10 lg:grid-cols-2">
        <div>
          <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-wine-700">
            Projeto Integrador · DSM · FATEC Araras
          </div>
          <h1 className="mt-3 text-4xl font-bold tracking-tight text-ink-900 sm:text-5xl">
            Torque
          </h1>
          <p className="mt-2 text-lg text-ink-700">
            Sistema de gestão para oficinas mecânicas
          </p>
          <p className="mt-4 max-w-xl text-sm leading-7 text-ink-600">
            O Torque reúne o atendimento de uma oficina de pequeno porte em um só lugar:
            clientes, veículos, ordens de serviço, orçamentos, peças e entrega. O sistema
            foi feito para a Auto Mecânica Leme como Projeto Integrador do curso de DSM.
          </p>

          <div className="mt-6 flex flex-wrap gap-2">
            <Link to="/documento" className="btn-primary">
              <BookOpen className="h-4 w-4" /> Documentação
            </Link>
            <Link to="/diagramas" className="btn-ghost">
              <Network className="h-4 w-4" /> Diagramas
            </Link>
            <a href="downloads/PI-ES2-2026.pdf" download className="btn-ghost">
              <Download className="h-4 w-4" /> PDF
            </a>
            <a
              href="https://github.com/odilklein/mecanica"
              target="_blank"
              rel="noreferrer"
              className="btn-ghost"
            >
              <Github className="h-4 w-4" /> GitHub
            </a>
          </div>

          <p className="mt-5 text-xs text-ink-500">
            {identificacao.grupo} · {identificacao.curso} · {identificacao.data}
          </p>
        </div>

        <figure className="card overflow-hidden">
          <img
            src="prints/print-02-dashboard.png"
            alt="Painel do Torque com indicadores da oficina"
            className="w-full"
          />
          <figcaption className="border-t border-line px-4 py-2 text-xs text-ink-500">
            Painel da oficina com dados de demonstração.
          </figcaption>
        </figure>
      </section>

      <section className="mt-14 border-t border-line pt-10">
        <div className="grid gap-8 lg:grid-cols-[0.7fr_1.3fr]">
          <div>
            <h2 className="text-2xl font-bold tracking-tight text-ink-900">
              O que o sistema faz
            </h2>
            <p className="mt-2 text-sm leading-6 text-ink-600">
              Módulos usados no dia a dia da oficina, do cadastro à entrega do veículo.
            </p>
          </div>
          <ul className="grid gap-x-8 gap-y-3 sm:grid-cols-2">
            {RECURSOS.map((item) => (
              <li key={item} className="flex items-start gap-2.5 text-[13px] leading-6 text-ink-700">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-wine-600" />
                {item}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="mt-14 border-t border-line pt-10">
        <div className="max-w-2xl">
          <h2 className="text-2xl font-bold tracking-tight text-ink-900">Documentação</h2>
          <p className="mt-2 text-sm leading-6 text-ink-600">
            O documento oficial segue o template da FATEC. Os diagramas podem ser abertos
            no navegador, com zoom e navegação.
          </p>
        </div>

        <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Link to="/documento" className="card group p-5 transition hover:border-wine-300">
            <FileText className="h-5 w-5 text-wine-600" />
            <h3 className="mt-3 text-sm font-bold text-ink-900">Documento oficial</h3>
            <p className="mt-1 text-[13px] leading-6 text-ink-600">
              {documento.toc.length} seções, {documento.requirements?.rf.length ?? 0} requisitos
              funcionais e {documento.requirements?.rnf.length ?? 0} não funcionais.
            </p>
            <span className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-wine-700">
              Abrir <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </span>
          </Link>

          <Link to="/diagramas" className="card group p-5 transition hover:border-wine-300">
            <Layers className="h-5 w-5 text-wine-600" />
            <h3 className="mt-3 text-sm font-bold text-ink-900">Diagramas</h3>
            <p className="mt-1 text-[13px] leading-6 text-ink-600">
              {diagrams.length} diagramas de casos de uso, classes, sequência, banco,
              arquitetura e fluxo.
            </p>
            <span className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-wine-700">
              Explorar <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </span>
          </Link>

          <Link
            to="/engenharia/07-seguranca-ofensiva"
            className="card group p-5 transition hover:border-wine-300"
          >
            <ShieldCheck className="h-5 w-5 text-wine-600" />
            <h3 className="mt-3 text-sm font-bold text-ink-900">Segurança</h3>
            <p className="mt-1 text-[13px] leading-6 text-ink-600">
              Varredura com HexStrike AI e Kali, 18 achados priorizados, correções e
              verificação posterior.
            </p>
            <span className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-wine-700">
              Ler o relatório{' '}
              <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </span>
          </Link>
        </div>
      </section>

      <section className="mt-14 border-t border-line pt-10">
        <div className="max-w-2xl">
          <h2 className="text-2xl font-bold tracking-tight text-ink-900">Equipe</h2>
          <p className="mt-2 text-sm leading-6 text-ink-600">
            A Team Zenitec desenvolveu o projeto para a Auto Mecânica Leme. Cada integrante
            ficou responsável por uma parte da entrega.
          </p>
        </div>

        <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {members.map((member) => (
            <MemberCard key={member.name} member={member} />
          ))}
        </div>
      </section>
    </div>
  )
}
