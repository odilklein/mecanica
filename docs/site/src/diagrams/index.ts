import type { DiagramDefinition } from './types'
import { casosDeUsoDiagrams } from './useCases'
import { classesDiagram } from './classes'
import { sequenciaDiagrams } from './sequences'
import { erDiagrams } from './er'
import { arquiteturaDiagram, fluxoOSDiagram } from './architecture'

export const diagrams: DiagramDefinition[] = [
  ...casosDeUsoDiagrams,
  classesDiagram,
  ...sequenciaDiagrams,
  ...erDiagrams,
  arquiteturaDiagram,
  fluxoOSDiagram,
].sort((a, b) => a.figure - b.figure)

export const diagramsById = new Map(diagrams.map((d) => [d.id, d]))

export function getDiagram(id: string): DiagramDefinition | undefined {
  return diagramsById.get(id)
}

export function diagramByFigure(figure: number): DiagramDefinition | undefined {
  return diagrams.find((d) => d.figure === figure)
}
