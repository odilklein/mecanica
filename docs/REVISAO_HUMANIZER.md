# Revisão de texto — Humanizer

Guia para revisar os textos da documentação antes de gerar o DOCX/PDF e antes de
publicar no site. A referência é o [Humanizer](https://github.com/blader/humanizer)
(MIT), um skill de agente baseado em
[Signs of AI writing](https://en.wikipedia.org/wiki/Wikipedia:Signs_of_AI_writing),
da Wikipédia.

O objetivo é simples: o texto precisa parecer escrito por uma pessoa para outra
pessoa. A banca e o cliente devem conseguir ler sem esbarrar em jargão de código,
em frase de propaganda ou em construção típica de modelo de linguagem.

## Como usar

1. Leia o arquivo inteiro antes de editar.
2. Marque os padrões da seção [O que remover](#o-que-remover).
3. Reescreva a frase ou o parágrafo inteiro, não só a palavra marcada.
4. Confira se nenhum fato, número, tabela, figura, link ou requisito mudou.
5. Rode `docs/build/render.sh documento` e confira o PDF.

Para pedir a revisão a um agente, use um destes caminhos:

```text
Humanize o texto de docs/entrega/03-sistema.md
```

ou, se o skill estiver instalado no projeto:

```text
/humanizer [texto ou caminho do arquivo]
```

O skill original fica em `.opencode/skills/humanizer/SKILL.md`. Este guia é a
versão curta em português, adaptada para o vocabulário do projeto.

## O que remover

### Frases de encenação

- **Não é X, é Y**: "Não é apenas um sistema, é uma nova forma de trabalhar."
  Diga o que o sistema faz.
- **Fecho de efeito**: "É isso que faz a diferença." "No fim, o que importa é."
  Se a frase repete o parágrafo anterior, corte.
- **Frase que finge profundidade**: "No cerne da questão", "o verdadeiro valor
  está em". Troque pela afirmação concreta.
- **Preâmbulo antes do ponto**: "Vale destacar que", "É importante ressaltar
  que", "Nesse sentido", "Dessa forma". Comece pelo fato.
- **Discussão com ninguém**: "Não se trata de defender X, mas..." Se ninguém
  levantou a objeção, não a responda.

### Ritmo por regra

- **Trio forçado**: três itens em toda frase para parecer completo. Use a
  quantidade que o conteúdo pede.
- **Aberturas repetidas**: várias frases seguidas começando com "O sistema...".
  Junte ou mude o sujeito.
- **Travessão como conectivo universal**: prefira ponto, vírgula, dois-pontos ou
  parênteses. Um travessão ocasional é humano; um por parágrafo não é.
- **Adjetivos empilhados**: "solução robusta, escalável e inovadora" não diz
  nada. Troque por um fato: "atende quatro perfis", "roda em Docker".
- **Voz passiva sem agente**: "Foi implementado..." quando se sabe quem fez.
  "A equipe implementou..." é mais claro.

### Inflação e autoridade emprestada

- **Palavras gastas**: robusto, abrangente, inovador, transformador, completo,
  ecossistema, jornada, protagonista, sinergia, de ponta, state-of-the-art.
- **Significado inflado**: "marca um momento decisivo", "revoluciona",
  "garante excelência". Mantenha o fato e corte a importância.
- **Conexão vaga**: "relacionado a", "no que diz respeito a", "em termos de".
  Nomeie a relação.
- **Autoridade sem nome**: "especialistas afirmam", "o mercado reconhece".
  Cite a fonte ou remova a afirmação.

### Formatação decorativa

- Negrito em toda etiqueta de lista. Prefira prosa ou uma lista sem rótulo.
- Títulos com todas as palavras em maiúscula, emoji ou seta. Use caixa de frase.
- Escrever sobre o documento: "Este capítulo tem como objetivo apresentar...",
  "A tabela abaixo mostra...". O leitor já vê a tabela.

### Restos de conversa e de rascunho

- "Ótima pergunta", "Espero ter ajudado", "Como vimos anteriormente".
- Repetir o título na primeira frase da seção.
- Explicar ao dev como o código funciona quando o assunto é o que o usuário faz.
  Em vez de "o controller valida o CSRF e persiste o registro", escreva "o
  sistema confere a origem do formulário e grava o registro" — **exceto** em
  seções explicitamente técnicas, onde o termo correto deve permanecer.

## Jargão de dev e de IA

Use esta troca quando a seção for de negócio ou de apresentação. Nas seções de
stack, segurança, integrações e qualidade, o termo técnico é o assunto e deve
ficar.

| Em vez de | Prefira |
|---|---|
| controller, endpoint, payload | a rota, a requisição, os dados enviados |
| persistir, gravar no banco | salvar, registrar |
| migration, seed | script de banco, carga inicial |
| deploy, setup, workflow, feature, bug | publicação, configuração, fluxo, funcionalidade, falha |
| CRUD, views, front controller | cadastros, telas, ponto de entrada |
| hash, token, sessão (em texto de negócio) | código de verificação, sessão do usuário |
| instanciar, orquestrar, abstrair | criar, coordenar, separar |
| LLM, prompt, agente (em texto de negócio) | assistente, instrução, assistente de IA |

## Checklist final

- [ ] Nenhum título, tabela, figura, link ou requisito foi alterado.
- [ ] Nenhum número, data, nome de tabela ou de arquivo foi inventado.
- [ ] Não há "não é apenas X, é Y", fecho de efeito ou preâmbulo vazio.
- [ ] Não há trio forçado nem travessão em excesso.
- [ ] Não há adjetivo de propaganda sem fato que o sustente.
- [ ] O texto diz o que o sistema faz para o usuário, não como o código é escrito
      (salvo nas seções técnicas).
- [ ] A leitura em voz alta não trava em frase longa ou traduzida do inglês.
- [ ] O PDF foi gerado de novo e o sumário continua correto.

## Fonte e licença

O Humanizer é distribuído sob licença MIT. O `SKILL.md` original está em
`.opencode/skills/humanizer/SKILL.md`, com o cabeçalho de origem preservado. Este
guia não substitui o skill: ele resume os padrões que mais aparecem nos textos
deste projeto.
