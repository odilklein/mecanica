#!/usr/bin/env node
/**
 * Prepara o conteúdo do site a partir dos arquivos Markdown do repositório.
 *
 * - Lê docs/entrega e docs/engenharia.
 * - Extrai os requisitos RF/RNF e troca os blocos por marcadores.
 * - Gera a tabela de conteúdos com os mesmos slugs do rehype-slug.
 * - Copia prints, logotipos e os downloads para docs/site/public.
 * - Escreve src/content/generated.ts.
 */
import fs from 'node:fs'
import path from 'node:path'
import GithubSlugger from 'github-slugger'

const SITE = process.cwd()
const DOCS = path.resolve(SITE, '..')
const OUT = path.join(SITE, 'src', 'content', 'generated.ts')
const PUBLIC = path.join(SITE, 'public')

const ORDEM_DOCUMENTO = [
  '01-empresa.md',
  '02-escopo.md',
  '03-sistema.md',
  '04-testes.md',
  '05-consideracoes.md',
  'referencias.md',
  'anexos/01-diario-de-bordo.md',
  'anexos/02-cronograma-efetivo.md',
  'anexos/03-evidencias.md',
]

const ORDEM_ENGENHARIA = [
  '01-stack.md',
  '02-requisitos.md',
  '03-regras-negocio.md',
  '04-modelo-dados.md',
  '05-arquitetura.md',
  '06-integracoes.md',
]

const ADRS = [
  'ADR-001-monolito-modular.md',
  'ADR-002-kernel-proprio.md',
  'ADR-003-mysql-migrations.md',
  'ADR-004-sessao-rbac.md',
  'ADR-005-ia-aprovacao-humana.md',
  'ADR-006-docker.md',
]

function ler(rel) {
  return fs.readFileSync(path.join(DOCS, rel), 'utf8').replace(/\r\n/g, '\n')
}

function tituloDe(markdown, fallback) {
  const m = markdown.match(/^#\s+(.+)$/m)
  return m ? m[1].trim() : fallback
}

/** Remove blocos RF/RNF e devolve o markdown com marcadores. */
function separarRequisitos(markdown) {
  const linhas = markdown.split('\n')
  const rf = []
  const rnf = []
  const saida = []
  let marcadorRF = false
  let marcadorRNF = false
  let i = 0
  while (i < linhas.length) {
    const linha = linhas[i]
    const m = linha.match(/^###\s+(R(?:F|NF)\d{2})\s+—\s+(.+?)\s+—\s+(Essencial|Importante|Desejável)\s*$/)
    if (m) {
      let j = i + 1
      while (j < linhas.length && !linhas[j].trim()) j++
      const descricao = (linhas[j] || '').trim()
      const item = { id: m[1], name: m[2], priority: m[3], description: descricao }
      if (m[1].startsWith('RNF')) {
        rnf.push(item)
        if (!marcadorRNF) {
          saida.push('[[RNF]]')
          marcadorRNF = true
        }
      } else {
        rf.push(item)
        if (!marcadorRF) {
          saida.push('[[RF]]')
          marcadorRF = true
        }
      }
      i = j + 1
      continue
    }
    saida.push(linha)
    i++
  }
  return { markdown: saida.join('\n'), rf, rnf }
}

function slugificar(markdown) {
  const slugger = new GithubSlugger()
  const toc = []
  for (const linha of markdown.split('\n')) {
    const m = linha.match(/^(#{1,3})\s+(.+?)\s*$/)
    if (!m) continue
    const nivel = m[1].length
    const texto = m[2].replace(/[*`]/g, '').trim()
    toc.push({ level: nivel, text: texto, id: slugger.slug(texto) })
  }
  return toc
}

function processar(markdown, slug, fallbackTitle, removerTitulo = false) {
  const normalizado = markdown
    .replaceAll('imagens/prints/', 'prints/')
    .replaceAll('imagens/logos/', 'logos/')
  const corpo = removerTitulo ? normalizado.replace(/^#\s+.+\n+/, '') : normalizado
  const { markdown: limpo, rf, rnf } = separarRequisitos(corpo)
  return {
    slug,
    title: tituloDe(markdown, fallbackTitle),
    markdown: limpo,
    toc: slugificar(limpo),
    requirements: rf.length || rnf.length ? { rf, rnf } : undefined,
  }
}

function lerIdentificacao() {
  const texto = ler('entrega/00-identificacao.md')
  const info = {}
  const integrantes = []
  let secao = ''
  for (const linha of texto.split('\n')) {
    if (linha.startsWith('## ')) {
      secao = linha.slice(3).trim().toLowerCase()
      continue
    }
    if (linha.startsWith('|') && !linha.includes('---')) {
      const cols = linha.split('|').map((c) => c.trim()).filter(Boolean)
      if (cols.length >= 2 && cols[0] !== 'Nome' && !cols[0].startsWith(':')) {
        if (secao.startsWith('integrantes')) {
          integrantes.push({ nome: cols[0], papel: cols[1] })
        }
      }
      continue
    }
    const kv = linha.match(/^([a-zA-Z_]+):\s*(.*)$/)
    if (kv) info[kv[1]] = kv[2].trim()
  }
  return { ...info, integrantes }
}

function copiarAssets() {
  fs.mkdirSync(PUBLIC, { recursive: true })
  const copiar = (origem, destino) => {
    if (!fs.existsSync(origem)) return
    fs.mkdirSync(destino, { recursive: true })
    for (const arquivo of fs.readdirSync(origem)) {
      const de = path.join(origem, arquivo)
      if (fs.statSync(de).isFile()) fs.copyFileSync(de, path.join(destino, arquivo))
    }
  }
  copiar(path.join(DOCS, 'imagens', 'prints'), path.join(PUBLIC, 'prints'))
  copiar(path.join(DOCS, 'imagens', 'logos'), path.join(PUBLIC, 'logos'))
  const downloads = path.join(PUBLIC, 'downloads')
  fs.mkdirSync(downloads, { recursive: true })
  for (const arquivo of ['PI-ES2-2026.pdf', 'PI-ES2-2026.docx']) {
    const origem = path.join(DOCS, 'saida', arquivo)
    if (fs.existsSync(origem)) fs.copyFileSync(origem, path.join(downloads, arquivo))
  }
}

function main() {
  const identificacao = lerIdentificacao()

  const partes = ORDEM_DOCUMENTO.map((rel) => ler(`entrega/${rel}`))
  const markdownDocumento = partes.join('\n\n')
  const documento = processar(markdownDocumento, 'documento', 'Documento oficial')

  const engenharia = ORDEM_ENGENHARIA.map((rel) => {
    const bruto = ler(`engenharia/${rel}`)
    return processar(bruto, rel.replace('.md', ''), rel, true)
  })

  const adrs = ADRS.map((rel) => {
    const bruto = ler(`engenharia/adr/${rel}`)
    return processar(bruto, `adr-${rel.replace('.md', '')}`, rel, true)
  })

  copiarAssets()
  fs.mkdirSync(path.dirname(OUT), { recursive: true })
  const conteudo = `/* Arquivo gerado por scripts/prepare-content.mjs — não editar. */
import type { DocumentContent, Identificacao } from '../lib/types'

export const identificacao: Identificacao = ${JSON.stringify(identificacao, null, 2)}

export const documento: DocumentContent = ${JSON.stringify(documento, null, 2)}

export const engenhariaDocs: DocumentContent[] = ${JSON.stringify(engenharia, null, 2)}

export const adrDocs: DocumentContent[] = ${JSON.stringify(adrs, null, 2)}
`
  fs.writeFileSync(OUT, conteudo, 'utf8')
  console.log(
    `conteúdo gerado: ${documento.toc.length} títulos, ` +
      `${documento.requirements?.rf.length ?? 0} RF, ` +
      `${documento.requirements?.rnf.length ?? 0} RNF, ` +
      `${engenharia.length} docs de engenharia, ${adrs.length} ADRs`,
  )
}

main()
