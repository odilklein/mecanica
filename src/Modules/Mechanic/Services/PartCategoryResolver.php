<?php
// =============================================
// RESOLVEDOR DE CATEGORIAS — Canal da Peça
// Converte a taxonomia do fornecedor (breadcrumb
// "Autopeças > Motor > Radiador & Arrefecimento")
// em uma categoria funcional do CRM de peças.
//
// A ordem das regras importa: a primeira que casar
// vence. Regras específicas (arrefecimento, freios,
// filtros) vêm antes das genéricas (motor, diversos).
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Services;

use PDO;

final class PartCategoryResolver
{
    /**
     * Regras em ordem de prioridade: slug => palavras-chave.
     * A comparação é feita sobre texto normalizado (sem acento, minúsculo).
     *
     * @var array<string, string[]>
     */
    private const RULES = [
        'arrefecimento' => [
            'radiador', 'arrefec', 'termostato', 'ventoinha', 'eletroventilador',
            'bomba de agua', 'bomba d agua', 'bomba d\'agua', 'mangueira de agua',
            'reservatorio de agua', 'coolant', 'colmeia', 'valvula termostatica',
        ],
        'freios' => [
            'freio', 'pastilha', 'disco de freio', 'lona', 'tambor', 'pinca',
            'cilindro de roda', 'servo freio', 'hidrovacuo', 'abs',
        ],
        'filtros' => [
            'filtro',
        ],
        'iluminacao' => [
            'farol', 'lanterna', 'lampada', 'sinaleira', 'pisca', 'xenon',
            'projetor', 'led automotivo',
        ],
        'eletrica' => [
            'bateria', 'alternador', 'motor de partida', 'partida', 'sensor',
            'ignicao', 'vela', 'bobina', 'fusivel', 'rele', 'chicote', 'modulo',
            'eletric', 'elétric', 'chave de seta', 'atuador', 'solenoide',
            'bomba de combustivel', 'bomba injetora',
        ],
        'suspensao' => [
            'suspens', 'amortecedor', 'mola', 'bandeja', 'pivo', 'rolamento',
            'cubo', 'bucha', 'terminal', 'braco', 'barra estabilizadora', 'coxim',
            'batente', 'direcao', 'caixa de direcao',
        ],
        'transmissao' => [
            'embreagem', 'cambio', 'transmiss', 'diferencial', 'correia',
            'homocinetica', 'semi-eixo', 'semi eixo', 'caixa de transferencia',
            'cardan', 'volante do motor', 'tensor',
        ],
        'motor' => [
            'motor', 'junta', 'retentor', 'pistao', 'biela', 'virabrequim',
            'cabecote', 'valvula', 'turbo', 'injecao', 'carburador',
            'bomba de oleo', 'coletor', 'escapamento', 'catalisador', 'silencioso',
            'escape', 'admissao', 'arvore de comando', 'comando de valvulas',
            'bronzina', 'camisa', 'segmento', 'anel',
        ],
        'pneus' => [
            'pneu', 'aro ', 'aro1', 'camara de ar', 'valvula de pneu',
        ],
        'carroceria' => [
            'carroceria', 'parachoque', 'para-choque', 'palheta', 'retrovisor',
            'vidro', 'porta', 'capo', 'grade', 'parabrisa', 'para-brisa',
            'paralama', 'macaneta', 'coluna', 'painel', 'forro', 'banco',
        ],
        'ferramentas' => [
            'ferramenta', 'maquina', 'equipamento', 'compressor', 'elevador',
            'macaco', 'chave de roda', 'solda', 'torno',
        ],
        'lubrificantes' => [
            'oleo', 'graxa', 'lubrificante', 'fluido', 'aditivo', 'silicone',
            'desengripante', 'limpa',
        ],
        'acessorios' => [
            'acessori', 'tuning', 'som', 'multimidia', 'engate', 'rack',
            'bagageiro', 'capa', 'pelicula', 'alarme', 'camera', 'tapete',
        ],
    ];

    /** Segmentos de breadcrumb que não identificam uma categoria real. */
    private const GENERIC_SEGMENTS = [
        'autopecas', 'autopeças', 'diversos', 'diversos (a classificar)', 'busca por codigo da peca',
        'busca por código da peça', 'componentes industriais', 'maquinas & ferramentas',
        'máquinas & ferramentas', 'oleos & fluidos', 'óleos & fluidos',
        'acessorios & tuning', 'acessórios & tuning', 'pneus & rodas', 'motor e partes',
        'equipamentos de protecao e uniformes', 'equipamentos de proteção e uniformes',
    ];

    /** Resolve a categoria funcional (slug) a partir de categoria + nome. */
    public static function resolve(string $context): string
    {
        $text = self::normalize($context);

        foreach (self::RULES as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, self::normalize($keyword))) {
                    return $slug;
                }
            }
        }

        return 'diversos';
    }

    /**
     * Extrai o rótulo mais específico do breadcrumb.
     * "Autopeças > Motor > Radiador & Arrefecimento" => "Radiador & Arrefecimento".
     * Retorna '' quando só há segmentos genéricos.
     */
    public static function leaf(string $category): string
    {
        $segments = array_map('trim', explode('>', $category));
        $segments = array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));

        $meaningful = [];
        foreach ($segments as $segment) {
            if (!in_array(self::normalize($segment), self::GENERIC_SEGMENTS, true)) {
                $meaningful[] = $segment;
            }
        }

        if (!$meaningful) {
            return '';
        }

        return mb_substr((string) end($meaningful), 0, 120);
    }

    /** @return array<string, int> slug => id */
    public static function ids(PDO $pdo): array
    {
        $map = [];
        $stmt = $pdo->query('SELECT id, slug FROM part_categories');
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(string) $row['slug']] = (int) $row['id'];
        }
        return $map;
    }

    /** @return array<string, string> slug => nome */
    public static function names(PDO $pdo): array
    {
        $map = [];
        $stmt = $pdo->query('SELECT slug, name FROM part_categories');
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(string) $row['slug']] = (string) $row['name'];
        }
        return $map;
    }

    /** Normaliza para comparação: minúsculo, sem acento e espaços repetidos. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['`', '´'], "'", $value);
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        return (string) preg_replace('/\s+/', ' ', $value);
    }
}
