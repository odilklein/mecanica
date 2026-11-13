<?php
// =============================================
// PREFILL SCHEMA — o que a IA pode preencher em cada formulário.
//
// Campos derivados (CEP→endereço, FIPE→código/valor, totais) ficam
// FORA do schema: quem preenche é a API/cascata do sistema, não o modelo.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

final class PrefillSchema
{
    /**
     * @return array<string, array{
     *   label: string, description: string, fields: array<string, array<string, mixed>>, derived: string[]
     * }>
     */
    public static function all(): array
    {
        return [
            'service_order' => [
                'label' => 'abertura de ordem de serviço',
                'description' => 'Cliente, veículo e queixa do atendimento. Endereço vem do CEP; FIPE vem da cascata.',
                'fields' => [
                    'client_name' => ['label' => 'Nome do cliente', 'type' => 'text'],
                    'client_phone' => ['label' => 'Telefone do cliente', 'type' => 'text'],
                    'client_email' => ['label' => 'E-mail do cliente', 'type' => 'text'],
                    'zip' => [
                        'label' => 'CEP',
                        'type' => 'text',
                        'hint' => 'somente o CEP; NÃO extraia rua, bairro, cidade ou UF',
                    ],
                    'address_number' => ['label' => 'Número do endereço', 'type' => 'text'],
                    'plate' => ['label' => 'Placa do veículo', 'type' => 'text'],
                    'brand' => ['label' => 'Marca do veículo', 'type' => 'text'],
                    'model' => ['label' => 'Modelo do veículo', 'type' => 'text'],
                    'year' => ['label' => 'Ano do veículo', 'type' => 'integer'],
                    'color' => ['label' => 'Cor do veículo', 'type' => 'text'],
                    'initial_complaint' => ['label' => 'Queixa relatada pelo cliente', 'type' => 'textarea'],
                    'priority' => [
                        'label' => 'Prioridade',
                        'type' => 'enum',
                        'enum' => ['low', 'normal', 'high', 'urgent'],
                        'hint' => 'baixa=low, normal=normal, alta=high, urgente=urgent',
                    ],
                    'internal_notes' => ['label' => 'Observações internas', 'type' => 'textarea'],
                ],
                'derived' => [
                    'address', 'neighborhood', 'city', 'state',
                    'fipe_brand_code', 'fipe_model_code', 'fipe_year_code', 'fipe_value',
                    'tasks_json', 'parts_json', 'client_id', 'vehicle_id',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
