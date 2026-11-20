import { useEffect, useState, type RefObject } from 'react'

export function useFullscreen(ref: RefObject<HTMLElement | null>) {
  const [telaCheia, setTelaCheia] = useState(false)

  useEffect(() => {
    const aoMudar = () => setTelaCheia(Boolean(document.fullscreenElement))
    document.addEventListener('fullscreenchange', aoMudar)
    return () => document.removeEventListener('fullscreenchange', aoMudar)
  }, [])

  const alternar = async () => {
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen()
      } else if (ref.current) {
        await ref.current.requestFullscreen()
      }
    } catch {
      // Alguns navegadores bloqueiam fullscreen fora de um gesto do usuário.
    }
  }

  return { telaCheia, alternar }
}
