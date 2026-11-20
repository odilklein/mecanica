<?php
// =============================================
// MODEL CATALOG — modelos Groq disponíveis no chat.
//
// O usuário escolhe o modelo no próprio chat. O catálogo
// concentra rótulo, capacidades (visão/reasoning), esforço
// suportado e preço para exibir custo estimado.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

final class ModelCatalog
{
    /**
     * Preços em USD por 1M de tokens (Groq, tabela de produção).
     *
     * @var array<string, array{
     *   label: string, hint: string, vision: bool, reasoning: bool,
     *   efforts: string[], default_effort: string, input: float, output: float
     * }>
     */
    public const MODELS = [
        'openai/gpt-oss-20b' => [
            'label' => 'GPT-OSS 20B',
            'hint' => 'Rápido e econômico',
            'vision' => false,
            'reasoning' => true,
            'efforts' => ['low', 'medium', 'high'],
            'default_effort' => 'low',
            'input' => 0.075,
            'output' => 0.30,
        ],
        'openai/gpt-oss-120b' => [
            'label' => 'GPT-OSS 120B',
            'hint' => 'Mais capaz em análises',
            'vision' => false,
            'reasoning' => true,
            'efforts' => ['low', 'medium', 'high'],
            'default_effort' => 'medium',
            'input' => 0.15,
            'output' => 0.60,
        ],
        'qwen/qwen3.8-27b' => [
            'label' => 'Qwen 3.8 27B',
            'hint' => 'Lê imagens e documentos',
            'vision' => true,
            'reasoning' => true,
            'efforts' => ['none', 'low', 'medium', 'high'],
            'default_effort' => 'none',
            'input' => 0.80,
            'output' => 4.00,
        ],
    ];

    /**
     * Modos do assistente exibidos no seletor do chat.
     * Cada modo aponta para um modelo e um nível de esforço.
     */
    public const MODES = [
        'fast' => [
            'label' => 'Rápido',
            'hint' => 'GPT-OSS 20B · rápido e econômico',
            'model' => 'openai/gpt-oss-20b',
            'effort' => 'low',
        ],
        'balanced' => [
            'label' => 'Equilibrado',
            'hint' => 'GPT-OSS 120B · mais contexto e análise',
            'model' => 'openai/gpt-oss-120b',
            'effort' => 'medium',
        ],
        'deep' => [
            'label' => 'Profundo',
            'hint' => 'GPT-OSS 120B · análise aprofundada',
            'model' => 'openai/gpt-oss-120b',
            'effort' => 'high',
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::MODELS;
    }

    /**
     * Modo padrão conforme o modelo configurado no ambiente.
     */
    public static function defaultMode(): string
    {
        $model = self::default();
        $effort = (string) (self::MODELS[$model]['default_effort'] ?? 'low');

        foreach (self::MODES as $key => $mode) {
            if ($mode['model'] === $model && $mode['effort'] === $effort) {
                return $key;
            }
        }

        return 'fast';
    }

    /**
     * Modos prontos para o seletor do chat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modesForFront(): array
    {
        $default = self::defaultMode();
        $items = [];

        foreach (self::MODES as $key => $mode) {
            $items[] = [
                'key' => $key,
                'label' => $mode['label'],
                'hint' => $mode['hint'],
                'model' => $mode['model'],
                'effort' => $mode['effort'],
                'is_default' => $key === $default,
            ];
        }

        return $items;
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $id): ?array
    {
        $id = trim((string) $id);

        return self::MODELS[$id] ?? null;
    }

    public static function exists(?string $id): bool
    {
        return self::find($id) !== null;
    }

    /**
     * Modelo padrão: env LLM_MODEL se existir no catálogo, senão o 20B.
     */
    public static function default(): string
    {
        $configured = trim((string) env('LLM_MODEL', ''));

        return self::exists($configured) ? $configured : 'openai/gpt-oss-20b';
    }

    /**
     * Modelo com visão (para turnos com imagem).
     */
    public static function vision(): string
    {
        $configured = trim((string) env('LLM_VISION_MODEL', ''));
        if (self::exists($configured) && !empty(self::MODELS[$configured]['vision'])) {
            return $configured;
        }

        return 'qwen/qwen3.8-27b';
    }

    /**
     * Normaliza o modelo pedido pelo cliente (allow-list).
     */
    public static function resolve(?string $id): string
    {
        return self::exists($id) ? (string) $id : self::default();
    }

    /**
     * Esforço válido para o modelo; cai no default quando inválido.
     */
    public static function effort(string $model, ?string $effort): string
    {
        $meta = self::find($model);
        if ($meta === null || empty($meta['reasoning'])) {
            return 'none';
        }

        $effort = trim((string) $effort);
        if ($effort !== '' && in_array($effort, $meta['efforts'], true)) {
            return $effort;
        }

        return (string) $meta['default_effort'];
    }

    /**
     * Custo estimado em USD (tokens).
     */
    public static function cost(string $model, int $tokensIn, int $tokensOut): float
    {
        $meta = self::find($model);
        if ($meta === null) {
            return 0.0;
        }

        return ($tokensIn / 1_000_000) * (float) $meta['input']
            + ($tokensOut / 1_000_000) * (float) $meta['output'];
    }

    /**
     * Catálogo pronto para o front (sem preços internos desnecessários).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forFront(): array
    {
        $default = self::default();
        $items = [];

        foreach (self::MODELS as $id => $meta) {
            $items[] = [
                'id' => $id,
                'label' => $meta['label'],
                'hint' => $meta['hint'],
                'vision' => (bool) $meta['vision'],
                'reasoning' => (bool) $meta['reasoning'],
                'efforts' => $meta['efforts'],
                'default_effort' => $meta['default_effort'],
                'is_default' => $id === $default,
            ];
        }

        return $items;
    }
}
