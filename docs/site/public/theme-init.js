/* Aplica o tema antes do React para evitar o flash de tela clara. */
(function () {
  try {
    var salvo = localStorage.getItem('theme');
    var escuro =
      salvo === 'dark' || (!salvo && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', escuro);
  } catch (e) {}
})();
