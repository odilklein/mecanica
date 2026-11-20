# Integrações externas

Todas as integrações são opcionais ou têm *fallback*: a indisponibilidade de um
serviço externo não derruba a operação da oficina.

## Resumo

| Serviço | Para quê | Endpoint | Autenticação | Cache |
|---|---|---|---|---|
| ViaCEP | Preencher endereço pelo CEP | `https://viacep.com.br/ws/{cep}/json/` | não | navegador |
| FIPE (parallelum) | Marca, modelo, ano e preço de referência | `https://fipe.parallelum.com.br/api/v1` | não | MySQL (`fipe_*`) |
| Canal da Peça | Catálogo, ofertas e compatibilidade de peças | `https://www.canaldapeca.com.br` | não | arquivo em `storage/cache/canaldapeca` + MySQL (`cdp_*`) |
| Groq | Assistente de IA, transcrição e textos assistidos | `https://api.groq.com/openai/v1` | `LLM_API_KEY` | não |
| Cloudflare Turnstile | Proteção do login contra automação | `https://challenges.cloudflare.com/turnstile/v0/siteverify` | `TURNSTILE_SECRET` | não |

## ViaCEP

- Implementado em `public/js/viacep.js`, executado no navegador.
- Dispara quando o campo CEP perde o foco, consulta o serviço e preenche
  logradouro, bairro, cidade e UF.
- O número do endereço continua obrigatório e é digitado pelo usuário.
- Se o CEP não existir ou o serviço falhar, o formulário permanece editável.

## FIPE

- Implementado em `Modules\Mechanic\Services\FipeService`.
- A base pode ser trocada por `FIPE_BASE_URL`.
- Estratégia *cache-aside*: consulta o banco (`fipe_brands`, `fipe_models`,
  `fipe_years`, `fipe_prices`) e só chama a API quando o dado não está local.
- O comando `bin/` de sincronização popula o cache em lote.
- Usado no cadastro de veículo para preencher marca, modelo, ano e valor de
  referência.

## Canal da Peça

- Implementado em `Modules\Mechanic\Services\CanalDaPecaClient`.
- Consulta o catálogo público e normaliza o resultado em `cdp_products`,
  `cdp_offers`, `cdp_vehicle_makes`, `cdp_vehicle_models` e
  `cdp_vehicle_years`.
- Cache em arquivo (`storage/cache/canaldapeca`, TTL de 10 min para busca e 24 h
  para veículos) e *throttle* entre requisições para não sobrecarregar o site.
- No sistema, alimenta a busca de peças, o preenchimento assistido e a escolha
  de peças dentro da OS.
- A indisponibilidade do catálogo não bloqueia o cadastro manual de peças.

## Groq (LLM)

- Implementado em `Modules\Agent\Services\LlmClient`, com API compatível com
  OpenAI.
- Variáveis: `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL`.
- Modelos disponíveis no chat (catálogo em `ModelCatalog`):
  `openai/gpt-oss-20b`, `openai/gpt-oss-120b` e `qwen/qwen3.8-27b`.
- Usos:
  - chat do assistente com *streaming* e chamada de ferramentas;
  - preenchimento assistido de cliente, veículo e nova OS;
  - conversão de ditado em queixa técnica;
  - geração de mensagem de WhatsApp para o cliente;
  - transcrição de áudio (`/api/transcribe`).
- O consumo é registrado em `agent_usage` (tokens de entrada e saída por dia).
- **Sem chave configurada**, o módulo fica inativo e a interface informa; nenhum
  outro recurso depende dele.

## Cloudflare Turnstile

- Implementado em `Modules\Core\Support\Turnstile`.
- A chave pública vem de `TURNSTILE_SITE_KEY`; a secreta, de `TURNSTILE_SECRET`.
- Sem as duas chaves, o captcha é desativado e o login continua funcionando.
- A validação acontece no servidor, em `siteverify`.

## Variáveis de ambiente

| Variável | Obrigatória | Uso |
|---|---|---|
| `APP_ENV`, `APP_DEBUG` | não | Ambiente e exibição de erros |
| `APP_KEY` | sim | Chave da aplicação |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | sim | Conexão MySQL |
| `FIPE_BASE_URL` | não | Trocar a base da FIPE |
| `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL` | não | Assistente de IA |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET` | não | Captcha do login |
| `LOGIN_MAX_TENTATIVAS`, `LOGIN_JANELA_MINUTOS` | não | Rate limit do login |

O arquivo `.env.example` lista todas as variáveis com valores de exemplo.
