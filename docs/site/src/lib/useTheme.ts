import { useEffect, useState } from 'react'

export type Theme = 'light' | 'dark'

export function useTheme() {
  const [tema, setTema] = useState<Theme>(() =>
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
      ? 'dark'
      : 'light',
  )

  useEffect(() => {
    setTema(document.documentElement.classList.contains('dark') ? 'dark' : 'light')
  }, [])

  const alternar = () => {
    const proximo: Theme = document.documentElement.classList.contains('dark')
      ? 'light'
      : 'dark'
    document.documentElement.classList.toggle('dark', proximo === 'dark')
    localStorage.setItem('theme', proximo)
    setTema(proximo)
  }

  return { tema, alternar }
}

export function useIsDark() {
  const [escuro, setEscuro] = useState(
    () => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
  )

  useEffect(() => {
    const observador = new MutationObserver(() => {
      setEscuro(document.documentElement.classList.contains('dark'))
    })
    observador.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
    })
    return () => observador.disconnect()
  }, [])

  return escuro
}
