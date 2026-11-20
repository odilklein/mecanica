import { useEffect, useState } from 'react'

export function useActiveHeading(ids: string[]) {
  const [ativo, setAtivo] = useState(ids[0] ?? '')

  useEffect(() => {
    if (!ids.length) return
    let frame = 0
    const atualizar = () => {
      frame = 0
      let atual = ids[0]
      for (const id of ids) {
        const el = document.getElementById(id)
        if (el && el.getBoundingClientRect().top <= 150) {
          atual = id
        }
      }
      setAtivo(atual)
    }
    const onScroll = () => {
      if (!frame) frame = window.requestAnimationFrame(atualizar)
    }
    atualizar()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [ids.join('|')])

  return ativo
}
