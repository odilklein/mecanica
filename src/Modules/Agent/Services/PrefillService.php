<?php
// =============================================
// PREFILL SERVICE — extrai campos do texto para o formulário.
//
// O modelo só devolve valores dos campos permitidos. Campos
// derivados (endereço via CEP, códigos/valores FIPE, totais)
// são preenchidos pelo próprio sistema depois.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use PDO;
use RuntimeException;

class PrefillService
{
    private PDO $pdo;
    private LlmClient $llm;

    public function __construct(PDO $pdo, LlmClient $llm)
    {
        $this->pdo = $pdo;
        $this->llm = $llm;
    }

    /**
     * @param array<string, mixed> $current Valores já digitados no formulário.
     * @return array{
     *   form: string,
     *   fields: array<string, mixed>,
     *   matches: array<string, mixed>,
     *   missing: string[],
     *   filled: int
     * }
     */
    public function extract(string $formKey, string $text, array $current = []): array
    {
        $schema = PrefillSchema::find($formKey);
        if ($schema === null) {
            throw new RuntimeException('Formulário não suportado pelo preenchimento com IA.');
        }

        $text = trim($text);
        if (mb_strlen($text) < 3) {
            throw new RuntimeException('Descreva os dados que deseja preencher.');
        }
        $text = mb_substr($text, 0, 4000);

        $data = $this->llm->json(
            [
                ['role' => 'system', 'content' => $this->systemPrompt($schema)],
                ['role' => 'user', 'content' => $this->userPrompt($text, $current)],
            ],
            ['max_tokens' => 700, 'temperature' => 0.1]
        );

        $raw = is_array($data['fields'] ?? null) ? $data['fields'] : $data;
        if (isset($raw['fields']) && is_array($raw['fields'])) {
            $raw = $raw['fields'];
        }

        $fields = [];
        foreach ($schema['fields'] as $key => $definition) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $value = $this->normalize($key, $definition, $raw[$key]);
            if ($value === null || $value === '') {
                continue;
            }
            $fields[$key] = $value;
        }

        // Defesa extra: nada derivado pode ser aceito do modelo.
        foreach ($schema['derived'] as $derived) {
            unset($fields[$derived]);
        }

        $matches = $this->matches($fields);
        $missing = array_values(array_diff(array_keys($schema['fields']), array_keys($fields)));

        return [
            'form' => $formKey,
            'fields' => $fields,
            'matches' => $matches,
            'missing' => $missing,
            'filled' => count($fields),
        ];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function systemPrompt(array $schema): string
    {
        $lines = [];
        foreach ($schema['fields'] as $key => $definition) {
            $line = '- ' . $key . ' (' . $definition['type'] . '): ' . $definition['label'];
            if (!empty($definition['hint'])) {
                $line .= ' — ' . $definition['hint'];
            }
            if (!empty($definition['enum'])) {
                $line .= ' [valores: ' . implode(', ', $definition['enum']) . ']';
            }
            $lines[] = $line;
        }

        return <<<PROMPT
Você extrai dados de um texto em português para preencher o formulário de {$schema['label']}.
{$schema['description']}

Responda SOMENTE com JSON válido no formato: {"fields": {"campo": "valor"}}.

REGRAS
- Inclua apenas os campos que o texto informa. Omita os demais.
- Nunca invente dados. Se não estiver claro, não preencha.
- Não preencha endereço, bairro, cidade ou UF: o sistema busca pelo CEP.
- Não preencha códigos FIPE, valor FIPE, totais, IDs nem listas de tarefas/peças.
- Placa em maiúsculas, sem espaços.
- Ano e quilometragem apenas números.
- Prioridade: baixa=low, normal=normal, alta=high, urgente=urgent.
- Preserve nomes e o sentido da queixa; corrija apenas pontuação.
- Se houver mais de uma pessoa ou veículo, use o primeiro.

CAMPOS PERMITIDOS
{$this->implodeLines($lines)}
PROMPT;
    }

    /** @param string[] $lines */
    private function implodeLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $current
     */
    private function userPrompt(string $text, array $current): string
    {
        $prompt = "Texto do usuário:\n\"\"\"\n{$text}\n\"\"\"";

        $current = array_filter($current, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
        if ($current) {
            $json = json_encode($current, JSON_UNESCAPED_UNICODE);
            $prompt .= "\n\nJá preenchido no formulário (não repita):\n" . ($json !== false ? $json : '');
        }

        return $prompt;
    }

    /**
     * @param array<string, mixed> $definition
     * @return string|int|null
     */
    private function normalize(string $key, array $definition, mixed $value)
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $type = (string) ($definition['type'] ?? 'text');
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if ($type === 'integer') {
            $digits = (int) preg_replace('/\D/', '', $text);

            return $digits > 0 ? $digits : null;
        }

        if ($type === 'enum') {
            $allowed = (array) ($definition['enum'] ?? []);
            $normalized = VehicleSpecService::normalize($text);

            $aliases = [
                'urgente' => 'urgent', 'alta' => 'high', 'media' => 'normal',
                'normal' => 'normal', 'baixa' => 'low', 'low' => 'low',
                'high' => 'high', 'urgent' => 'urgent',
            ];

            $mapped = $aliases[$normalized] ?? $normalized;

            return in_array($mapped, $allowed, true) ? $mapped : null;
        }

        switch ($key) {
            case 'zip':
                $digits = preg_replace('/\D/', '', $text) ?? '';
                if (strlen($digits) !== 8) {
                    return null;
                }

                return substr($digits, 0, 5) . '-' . substr($digits, 5);

            case 'cpf':
                $digits = preg_replace('/\D/', '', $text) ?? '';
                if (strlen($digits) !== 11) {
                    return $text;
                }

                return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.'
                    . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);

            case 'plate':
                $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $text) ?? '');
                if (strlen($plate) < 6 || strlen($plate) > 8) {
                    return null;
                }

                return $plate;

            case 'phone':
                $digits = preg_replace('/\D/', '', $text) ?? '';
                if (strlen($digits) === 11) {
                    return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
                }
                if (strlen($digits) === 10) {
                    return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
                }

                return mb_substr($text, 0, 20);

            case 'email':
                $email = mb_strtolower($text);

                return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        $max = $type === 'textarea' ? 4000 : 200;

        return mb_substr((string) preg_replace('/\s+/', ' ', $text), 0, $max);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function matches(array $fields): array
    {
        $matches = [];

        $clientName = trim((string) ($fields['client_name'] ?? $fields['name'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($fields['client_phone'] ?? $fields['phone'] ?? '')) ?? '';
        $email = trim((string) ($fields['email'] ?? ''));

        if ($clientName !== '' || $phone !== '' || $email !== '') {
            $sql = 'SELECT id, name, phone FROM clients WHERE is_active = 1 AND (';
            $where = [];
            $params = [];

            if ($clientName !== '') {
                $where[] = 'LOWER(name) = LOWER(:name)';
                $params[':name'] = $clientName;
            }
            if ($phone !== '') {
                $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '(', ''), ')', ''), '-', ''), ' ', '') = :phone";
                $params[':phone'] = $phone;
            }
            if ($email !== '') {
                $where[] = 'LOWER(email) = LOWER(:email)';
                $params[':email'] = $email;
            }

            $stmt = $this->pdo->prepare($sql . implode(' OR ', $where) . ') LIMIT 1');
            $stmt->execute($params);
            if ($row = $stmt->fetch()) {
                $matches['client_id'] = (int) $row['id'];
                $matches['client_name'] = (string) $row['name'];
            } elseif ($clientName !== '' && mb_strlen($clientName) >= 5) {
                // Busca parcial: só aceita quando existe exatamente um cliente.
                $partial = $this->pdo->prepare(
                    'SELECT id, name FROM clients WHERE is_active = 1 AND name LIKE :term ORDER BY name LIMIT 2'
                );
                $partial->execute([':term' => '%' . $clientName . '%']);
                $rows = $partial->fetchAll();
                if (count($rows) === 1) {
                    $matches['client_id'] = (int) $rows[0]['id'];
                    $matches['client_name'] = (string) $rows[0]['name'];
                }
            }
        }

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($fields['plate'] ?? '')) ?? '');
        if ($plate !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT id, client_id, plate, brand, model, year
                   FROM vehicles
                  WHERE is_active = 1
                    AND UPPER(REPLACE(REPLACE(REPLACE(plate, '-', ''), ' ', ''), '.', '')) = :plate
                  LIMIT 1"
            );
            $stmt->execute([':plate' => $plate]);
            if ($row = $stmt->fetch()) {
                $belongsToClient = !isset($matches['client_id'])
                    || (int) $row['client_id'] === (int) $matches['client_id'];

                if ($belongsToClient) {
                    $matches['vehicle_id'] = (int) $row['id'];
                    $matches['vehicle_label'] = trim(
                        (string) $row['plate'] . ' ' . (string) $row['brand'] . ' ' . (string) $row['model']
                    );
                    if (!isset($matches['client_id']) && $row['client_id'] !== null) {
                        $matches['client_id'] = (int) $row['client_id'];
                    }
                }
            }
        }

        return $matches;
    }
}
