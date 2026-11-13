<?php
// =============================================
// LLM CLIENT — cliente OpenAI-compatible (Groq por padrão)
// com streaming SSE, tool calling e reasoning.
//
// Groq:
//  - GPT-OSS expõe o raciocínio em `delta.reasoning`
//    (controlado por include_reasoning);
//  - Qwen 3.8 aceita reasoning_format=parsed|raw|hidden;
//    com tool calling, `raw` retorna 400.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use RuntimeException;

class LlmClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $configuredModel;
    private int $defaultMaxTokens;
    private float $defaultTemperature;

    public function __construct()
    {
        $this->apiKey = trim((string) env('LLM_API_KEY', env('GROQ_API_KEY', '')));
        $this->baseUrl = rtrim(
            (string) env('LLM_BASE_URL', 'https://api.groq.com/openai/v1'),
            '/'
        );
        $this->configuredModel = trim((string) env('LLM_MODEL', 'openai/gpt-oss-20b'));
        $this->defaultMaxTokens = max(256, (int) env('LLM_MAX_TOKENS', '1024'));
        $this->defaultTemperature = max(0.0, min(2.0, (float) env('LLM_TEMPERATURE', '0.3')));
    }

    public function mock(): bool
    {
        return filter_var(env('AGENT_MOCK', '0'), FILTER_VALIDATE_BOOL);
    }

    public function enabled(): bool
    {
        return $this->mock() || $this->apiKey !== '';
    }

    public function model(?string $requested = null): string
    {
        return ModelCatalog::resolve($requested ?? $this->configuredModel);
    }

    /**
     * Executa um turno com streaming.
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array{model?: string, effort?: string, reasoning?: bool, max_tokens?: int, temperature?: float} $options
     * @param callable(string):(void|bool) $onDelta      Retornar false aborta.
     * @param callable(string):(void|bool)|null $onReasoning
     *
     * @return array{
     *   content: string,
     *   reasoning: string,
     *   tool_calls: array<int, array{id: string, name: string, arguments: string}>,
     *   usage: array{in: int, out: int},
     *   model: string
     * }
     */
    public function stream(
        array $messages,
        array $tools,
        array $options,
        callable $onDelta,
        ?callable $onReasoning = null
    ): array {
        if ($this->apiKey === '') {
            throw new RuntimeException('Chave do provedor de IA não configurada. Defina GROQ_API_KEY no .env.');
        }

        $model = ModelCatalog::resolve($options['model'] ?? null);
        $meta = ModelCatalog::find($model) ?? [];
        $effort = ModelCatalog::effort($model, $options['effort'] ?? null);
        $wantReasoning = (bool) ($options['reasoning'] ?? false) && !empty($meta['reasoning']);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'max_completion_tokens' => max(256, (int) ($options['max_tokens'] ?? $this->defaultMaxTokens)),
            'temperature' => (float) ($options['temperature'] ?? $this->defaultTemperature),
            'top_p' => 0.95,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if ($effort !== '' && $effort !== 'none') {
            $payload['reasoning_effort'] = $effort;
        } elseif ($model === 'qwen/qwen3.8-27b') {
            // Qwen aceita "none" explicitamente; GPT-OSS não.
            $payload['reasoning_effort'] = 'none';
        }

        if ($wantReasoning) {
            if (str_starts_with($model, 'openai/gpt-oss')) {
                $payload['include_reasoning'] = true;
            } else {
                $payload['reasoning_format'] = 'parsed';
            }
        } else {
            if (str_starts_with($model, 'openai/gpt-oss')) {
                $payload['include_reasoning'] = false;
            } else {
                $payload['reasoning_format'] = 'hidden';
            }
        }

        $content = '';
        $reasoning = '';
        $toolCalls = [];
        $usage = ['in' => 0, 'out' => 0];
        $buffer = '';
        $rawBody = '';
        $httpStatus = 0;

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (
                &$buffer,
                &$content,
                &$reasoning,
                &$toolCalls,
                &$usage,
                &$rawBody,
                $onDelta,
                $onReasoning
            ): int {
                $rawBody .= $chunk;
                if (strlen($rawBody) > 200_000) {
                    $rawBody = substr($rawBody, -200_000);
                }

                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        continue;
                    }

                    $event = json_decode($data, true);
                    if (!is_array($event)) {
                        continue;
                    }

                    // Groq devolve o consumo real no bloco x_groq do último chunk.
                    $usageBlock = $event['x_groq']['usage'] ?? $event['usage'] ?? null;
                    if (is_array($usageBlock)) {
                        $usage['in'] = (int) ($usageBlock['prompt_tokens'] ?? $usage['in']);
                        $usage['out'] = (int) ($usageBlock['completion_tokens'] ?? $usage['out']);
                    }

                    $delta = $event['choices'][0]['delta'] ?? null;
                    if (!is_array($delta)) {
                        continue;
                    }

                    $thinking = (string) ($delta['reasoning'] ?? $delta['reasoning_content'] ?? '');
                    if ($thinking !== '' && $onReasoning !== null) {
                        $reasoning .= $thinking;
                        if ($onReasoning($thinking) === false) {
                            return 0;
                        }
                    }

                    if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                        $content .= $delta['content'];
                        if ($onDelta($delta['content']) === false) {
                            return 0;
                        }
                    }

                    foreach ((array) ($delta['tool_calls'] ?? []) as $call) {
                        $index = (int) ($call['index'] ?? 0);
                        $toolCalls[$index] ??= ['id' => '', 'name' => '', 'arguments' => ''];

                        if (!empty($call['id'])) {
                            $toolCalls[$index]['id'] = (string) $call['id'];
                        }
                        if (!empty($call['function']['name'])) {
                            $toolCalls[$index]['name'] = (string) $call['function']['name'];
                        }
                        if (isset($call['function']['arguments']) && is_string($call['function']['arguments'])) {
                            $toolCalls[$index]['arguments'] .= $call['function']['arguments'];
                        }
                    }
                }

                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($error !== '' && str_contains($error, 'Failed writing body')) {
            $error = '';
        }
        if ($error !== '' && $httpStatus === 0) {
            throw new RuntimeException('Falha de conexão com o provedor de IA: ' . $error);
        }
        if ($httpStatus >= 400) {
            throw new RuntimeException($this->errorMessage($httpStatus, $rawBody));
        }
        if ($content === '' && $reasoning === '' && !$toolCalls) {
            $detail = $this->errorMessage($httpStatus, $rawBody);
            throw new RuntimeException($detail !== '' ? $detail : 'O provedor não retornou conteúdo.');
        }

        if ($usage['in'] === 0) {
            $usage['in'] = (int) ceil(mb_strlen((string) json_encode($messages)) / 4);
        }
        if ($usage['out'] === 0) {
            $usage['out'] = (int) ceil(mb_strlen($content . $reasoning) / 4);
        }

        return [
            'content' => $content,
            'reasoning' => $reasoning,
            'tool_calls' => array_values(array_filter(
                $toolCalls,
                static fn (array $call): bool => $call['name'] !== ''
            )),
            'usage' => $usage,
            'model' => $model,
        ];
    }

    /**
     * Chamada não-streaming em JSON (usada pelo preenchimento de formulário).
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array{model?: string, max_tokens?: int, temperature?: float} $options
     * @return array<string, mixed>
     */
    public function json(array $messages, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Chave do provedor de IA não configurada.');
        }

        $model = ModelCatalog::resolve($options['model'] ?? null);
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'max_completion_tokens' => max(256, (int) ($options['max_tokens'] ?? 800)),
            'temperature' => (float) ($options['temperature'] ?? 0.1),
            'response_format' => ['type' => 'json_object'],
        ];

        if (str_starts_with($model, 'openai/gpt-oss')) {
            $payload['include_reasoning'] = false;
        } else {
            $payload['reasoning_format'] = 'hidden';
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '' || $body === false) {
            throw new RuntimeException('Falha de conexão com o provedor de IA.');
        }
        if ($status >= 400) {
            throw new RuntimeException($this->errorMessage($status, (string) $body));
        }

        $decoded = json_decode((string) $body, true);
        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        $content = trim($content);

        // Alguns modelos embrulham o JSON em bloco de código.
        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $content);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException('O modelo não devolveu um JSON válido.');
        }

        return $data;
    }

    private function errorMessage(int $status, string $rawBody): string
    {
        $detail = trim($rawBody);
        $decoded = json_decode($detail, true);
        if (is_array($decoded)) {
            $detail = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $detail);
        }

        if ($status === 429) {
            $wait = '';
            if (preg_match('/try again in ([0-9.]+\s*(?:s|ms|m))/i', $detail, $match)) {
                $wait = ' Aguarde cerca de ' . $match[1] . '.';
            }

            return 'Limite de uso do provedor atingido.' . $wait
                . ($wait === '' ? ' Tente novamente em instantes.' : '');
        }
        if ($status >= 400) {
            return 'Provedor de IA respondeu HTTP ' . $status . ': ' . mb_substr($detail, 0, 300);
        }

        return '';
    }
}
