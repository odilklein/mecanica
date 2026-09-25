<?php
// =============================================
// INTEGRAÇÃO FIPE (catálogo local + API Parallelum)
// Fonte: https://fipe.parallelum.com.br/api/v1 (base comunitária)
// O catálogo é espelhado no banco; a API só é usada no sync e no
// cálculo de preço (com cache em fipe_precos).
// =============================================

if (!function_exists('fipe_base_url')) {
    function fipe_base_url(): string
    {
        $url = function_exists('env') ? (string) env('FIPE_BASE_URL') : getenv('FIPE_BASE_URL');
        if ($url === '' || $url === false) {
            $url = 'https://fipe.parallelum.com.br/api/v1';
        }
        return rtrim($url, '/');
    }
}

if (!function_exists('fipe_http_json')) {
    // Busca uma URL e devolve o JSON decodificado (array) ou null em erro.
    // Em caso de HTTP 429 (rate limit), tenta de novo com backoff.
    function fipe_http_json(string $url): ?array
    {
        $tentativas = 3;

        for ($i = 0; $i < $tentativas; $i++) {
            $corpo = null;

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_USERAGENT => 'SistemaMecanica/1.0',
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $resposta = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $erro = curl_error($ch);
                curl_close($ch);

                if ($resposta === false || $status >= 400) {
                    @error_log('FIPE HTTP ' . $status . ' em ' . $url . ($erro ? ' | ' . $erro : ''));
                    if ($status === 429 && $i < $tentativas - 1) {
                        usleep(($i + 1) * 800000); // 0.8s, 1.6s de espera
                        continue;
                    }
                    return null;
                }
                $corpo = $resposta;
            } else {
                $contexto = stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'header' => "Accept: application/json\r\nUser-Agent: SistemaMecanica/1.0\r\n",
                    ],
                ]);
                $corpo = @file_get_contents($url, false, $contexto);
                if ($corpo === false) {
                    @error_log('FIPE file_get_contents falhou em ' . $url);
                    return null;
                }
            }

            $dados = json_decode((string) $corpo, true);
            if (is_array($dados)) {
                return $dados;
            }
            return null;
        }

        return null;
    }
}

if (!function_exists('fipe_aliases_marca')) {
    // Mapeia o nome da marca como vem da FIPE para o nome "amigável".
    function fipe_aliases_marca(): array
    {
        return [
            'GM - Chevrolet' => 'Chevrolet',
            'VW - VolksWagen' => 'Volkswagen',
            'Kia Motors' => 'Kia',
            'Caoa Chery' => 'Caoa Chery',
            'Caoa Chery/Chery' => 'Caoa Chery',
            'Caoa Changan' => 'Caoa Changan',
            'MINI' => 'Mini',
            'smart' => 'Smart',
            'ASTON MARTIN' => 'Aston Martin',
            'CHANA' => 'Chana',
            'CHANGAN' => 'Changan',
            'GREAT WALL' => 'Great Wall',
            'HAFEI' => 'Hafei',
            'HITECH ELECTRIC' => 'Hitech Electric',
            'LAMBORGHINI' => 'Lamborghini',
            'SSANGYONG' => 'SsangYong',
            'Mercedes-Benz' => 'Mercedes-Benz',
            'Land Rover' => 'Land Rover',
            'Rolls-Royce' => 'Rolls-Royce',
        ];
    }
}

if (!function_exists('fipe_nome_base')) {
    // Deriva o "modelo-base" (família) a partir do nome da versão FIPE.
    // Ex.: "ARGO DRIVE 1.0 6V Flex" -> "Argo"; "COROLLA CROSS XRE" -> "Corolla Cross".
    function fipe_nome_base(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'Outros';
        }

        // Usa apenas o primeiro segmento antes de barra/hífen de separação
        $partes = preg_split('/\s*[\/]\s*/', $nome);
        $primeiro = trim((string) ($partes[0] ?? $nome));
        $tokens = preg_split('/\s+/', $primeiro) ?: [];

        $stop = [
            'DRIVE', 'SPORT', 'SPORTING', 'EVO', 'EVOLUTION', 'ZEN', 'INTENSE', 'COMFORT',
            'ATTRACTIVE', 'ATTRACTION', 'ELX', 'EL', 'EX', 'LX', 'GL', 'GLX', 'GLI', 'XEI',
            'GT', 'GTX', 'GLS', 'ACTIVE', 'AUDACE', 'IMPETUS', 'FREEDOM', 'RANCH', 'ULTRA',
            'VOLCANO', 'ENDURANCE', 'TREKKING', 'ADVENTURE', 'WAY', 'MILLE', 'CITY', 'LIFE',
            'LIKE', 'EASY', 'BLACKMOTION', 'CULT', 'ABARTH', 'TURBO', 'FLEX', 'HGT',
            'ESSENCE', 'PRECISION', 'PLUS', 'TOP', 'START', 'LONGITUDE', 'LIMITED',
            'TRAILHAWK', 'NORTH', 'VISION', 'SENSE', 'FEEL', 'TITANIUM', 'TREND', 'SE',
            'SEL', 'HIGHLINE', 'COMFORTLINE', 'HIGHLANDER', 'XLS', 'XLT', 'LARAMIE',
            'RENEGADE', 'WILDTRAK', 'STORM', 'HIGH', 'LOW', 'CABINE', 'SEDAN', 'HATCH',
            'AWD', '4X4', '4X2', 'LE', 'XLE', 'XSE', 'PREMIUM', 'ADVANCE', 'ADV',
            'TOURING', 'CLASSIC', 'AMG', 'AVANTGARDE', 'STYLE', 'EXCLUSIVE', 'NIGHT',
            'LT', 'LTZ', 'LS', 'DL', 'EXL', 'EXS', 'ADVANTAGE', 'ADVANT', 'EDITION',
            'LO', 'LXS', 'SPORTIVE', 'DYNAMIC', 'ELEGANCE', 'PRIME', 'FAMILY', 'COMPLETE',
            'STD', 'URBAN', 'WALK',
        ];

        $base = [$tokens[0] ?? $nome];
        for ($i = 1; $i < count($tokens) && $i < 2; $i++) {
            $token = $tokens[$i];
            if (preg_match('/\d/', $token)) {
                break;
            }
            if (in_array(strtoupper(rtrim($token, '.')), $stop, true)) {
                break;
            }
            $base[] = $token;
        }

        $base = rtrim(implode(' ', $base), '. ');
        $base = mb_convert_case(mb_strtolower($base, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        return $base !== '' ? $base : 'Outros';
    }
}

if (!function_exists('fipe_coluna_existe')) {
    function fipe_coluna_existe(PDO $conn, string $tabela, string $coluna): bool
    {
        $st = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $tabela, ':c' => $coluna]);
        return (int) $st->fetchColumn() > 0;
    }
}

if (!function_exists('fipe_fk_existe')) {
    function fipe_fk_existe(PDO $conn, string $tabela, string $constraint): bool
    {
        $st = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
        $st->execute([':t' => $tabela, ':c' => $constraint]);
        return (int) $st->fetchColumn() > 0;
    }
}

if (!function_exists('fipe_garantir_schema')) {
    // Cria as tabelas do catálogo e adiciona as colunas opcionais em veiculos.
    // Idempotente: pode rodar em bancos já existentes.
    function fipe_garantir_schema(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS fipe_marcas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fipe_codigo VARCHAR(10) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            nome_exibicao VARCHAR(100) NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_marca_codigo (fipe_codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS fipe_marcas_alias (
            nome_fipe VARCHAR(100) PRIMARY KEY,
            nome_exibicao VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS fipe_modelos_base (
            id INT AUTO_INCREMENT PRIMARY KEY,
            marca_id INT NOT NULL,
            nome_base VARCHAR(120) NOT NULL,
            UNIQUE KEY uq_fipe_base (marca_id, nome_base),
            FOREIGN KEY (marca_id) REFERENCES fipe_marcas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS fipe_modelos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            base_id INT NOT NULL,
            fipe_codigo VARCHAR(20) NOT NULL,
            nome_versao VARCHAR(150) NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_modelo (base_id, fipe_codigo),
            FOREIGN KEY (base_id) REFERENCES fipe_modelos_base(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS fipe_precos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            modelo_id INT NOT NULL,
            ano_codigo VARCHAR(20) NOT NULL,
            ano INT NULL,
            combustivel VARCHAR(30) NULL,
            valor DECIMAL(10,2) NULL,
            codigo_fipe VARCHAR(20) NULL,
            mes_referencia VARCHAR(20) NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_preco (modelo_id, ano_codigo),
            FOREIGN KEY (modelo_id) REFERENCES fipe_modelos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Colunas opcionais em veiculos (FK + denormalização)
        $colunas = [
            'marca_id' => "ALTER TABLE veiculos ADD COLUMN marca_id INT NULL AFTER cor",
            'modelo_base_id' => "ALTER TABLE veiculos ADD COLUMN modelo_base_id INT NULL AFTER marca_id",
            'modelo_id' => "ALTER TABLE veiculos ADD COLUMN modelo_id INT NULL AFTER modelo_base_id",
            'fipe_versao' => "ALTER TABLE veiculos ADD COLUMN fipe_versao VARCHAR(150) NULL AFTER modelo_id",
            'valor_fipe' => "ALTER TABLE veiculos ADD COLUMN valor_fipe DECIMAL(10,2) NULL AFTER fipe_versao",
            'fipe_mes_referencia' => "ALTER TABLE veiculos ADD COLUMN fipe_mes_referencia VARCHAR(20) NULL AFTER valor_fipe",
        ];
        foreach ($colunas as $coluna => $ddl) {
            if (!fipe_coluna_existe($conn, 'veiculos', $coluna)) {
                $conn->exec($ddl);
            }
        }

        $fks = [
            'fk_veiculos_marca_fipe' => "ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_marca_fipe FOREIGN KEY (marca_id) REFERENCES fipe_marcas(id) ON DELETE SET NULL",
            'fk_veiculos_modelo_base_fipe' => "ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_modelo_base_fipe FOREIGN KEY (modelo_base_id) REFERENCES fipe_modelos_base(id) ON DELETE SET NULL",
            'fk_veiculos_modelo_fipe' => "ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_modelo_fipe FOREIGN KEY (modelo_id) REFERENCES fipe_modelos(id) ON DELETE SET NULL",
        ];
        foreach ($fks as $nome => $ddl) {
            if (!fipe_fk_existe($conn, 'veiculos', $nome)) {
                $conn->exec($ddl);
            }
        }
    }
}

if (!function_exists('fipe_dados_modelo')) {
    // Retorna [marca_codigo, modelo_codigo] para um modelo local, ou null.
    function fipe_dados_modelo(PDO $conn, int $modeloId): ?array
    {
        $st = $conn->prepare("SELECT m.fipe_codigo AS modelo_codigo, ma.fipe_codigo AS marca_codigo
            FROM fipe_modelos m
            JOIN fipe_modelos_base b ON b.id = m.base_id
            JOIN fipe_marcas ma ON ma.id = b.marca_id
            WHERE m.id = :id");
        $st->execute([':id' => $modeloId]);
        $linha = $st->fetch();
        return $linha ?: null;
    }
}

if (!function_exists('fipe_valor_por_modelo_ano')) {
    // Preço de referência FIPE para um modelo local + ano (com cache).
    // Retorna ['valor' => float, 'mes_referencia' => string, 'combustivel' => string] ou null.
    function fipe_valor_por_modelo_ano(PDO $conn, int $modeloId, int $ano): ?array
    {
        if ($modeloId <= 0 || $ano <= 0) {
            return null;
        }

        // 1) Cache: já temos algum preço para esse modelo/ano?
        $st = $conn->prepare("SELECT valor, combustivel, mes_referencia FROM fipe_precos
            WHERE modelo_id = :m AND ano = :a AND valor IS NOT NULL
            ORDER BY FIELD(combustivel, 'Flex', 'Gasolina', 'Diesel', 'Álcool', 'Híbrido', 'Elétrico', 'Gás Natural') , id
            LIMIT 1");
        $st->execute([':m' => $modeloId, ':a' => $ano]);
        $cache = $st->fetch();
        if ($cache) {
            return [
                'valor' => (float) $cache['valor'],
                'mes_referencia' => (string) $cache['mes_referencia'],
                'combustivel' => (string) $cache['combustivel'],
            ];
        }

        // 2) Descobre códigos FIPE
        $dados = fipe_dados_modelo($conn, $modeloId);
        if (!$dados) {
            return null;
        }

        // 3) Lista de anos do modelo
        $urlAnos = fipe_base_url() . '/carros/marcas/' . rawurlencode($dados['marca_codigo'])
            . '/modelos/' . rawurlencode($dados['modelo_codigo']) . '/anos';
        $anos = fipe_http_json($urlAnos);
        if (!$anos) {
            return null;
        }

        // 4) Escolhe o ano desejado (prefere Flex, senão o primeiro)
        $candidatos = [];
        foreach ($anos as $opcao) {
            $codigo = (string) ($opcao['codigo'] ?? '');
            $nome = (string) ($opcao['nome'] ?? '');
            if ($codigo === '' || !preg_match('/^(\d{4})/', $nome, $m)) {
                continue;
            }
            if ((int) $m[1] === $ano) {
                $candidatos[] = ['codigo' => $codigo, 'nome' => $nome];
            }
        }
        if (!$candidatos) {
            return null;
        }
        usort($candidatos, function ($a, $b) {
            $pa = stripos($a['nome'], 'Flex') !== false ? 0 : 1;
            $pb = stripos($b['nome'], 'Flex') !== false ? 0 : 1;
            return $pa <=> $pb;
        });
        $escolhido = $candidatos[0];

        // 5) Consulta o preço
        $urlValor = $urlAnos . '/' . rawurlencode($escolhido['codigo']);
        $detalhe = fipe_http_json($urlValor);
        if (!$detalhe || empty($detalhe['Valor'])) {
            return null;
        }

        $valor = fipe_converter_moeda((string) $detalhe['Valor']);
        $mesRef = (string) ($detalhe['MesReferencia'] ?? '');
        $combustivel = (string) ($detalhe['Combustivel'] ?? '');
        $codigoFipe = (string) ($detalhe['CodigoFipe'] ?? '');

        // 6) Grava no cache
        $st = $conn->prepare("INSERT INTO fipe_precos (modelo_id, ano_codigo, ano, combustivel, valor, codigo_fipe, mes_referencia)
            VALUES (:m, :ac, :ano, :comb, :valor, :cf, :mes)
            ON DUPLICATE KEY UPDATE ano = VALUES(ano), combustivel = VALUES(combustivel),
                valor = VALUES(valor), codigo_fipe = VALUES(codigo_fipe), mes_referencia = VALUES(mes_referencia)");
        $st->execute([
            ':m' => $modeloId,
            ':ac' => $escolhido['codigo'],
            ':ano' => $ano,
            ':comb' => mb_substr($combustivel, 0, 30),
            ':valor' => $valor,
            ':cf' => mb_substr($codigoFipe, 0, 20),
            ':mes' => mb_substr($mesRef, 0, 20),
        ]);

        return ['valor' => $valor, 'mes_referencia' => $mesRef, 'combustivel' => $combustivel];
    }
}

if (!function_exists('fipe_converter_moeda')) {
    // "R$ 89.123,00" -> 89123.00
    function fipe_converter_moeda(string $texto): float
    {
        $limpo = preg_replace('/[^\d,\.]/', '', $texto);
        if ($limpo === null || $limpo === '') {
            return 0.0;
        }
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
        return (float) $limpo;
    }
}

if (!function_exists('fipe_sincronizar')) {
    /**
     * Espelha o catálogo FIPE (marcas, modelos-base e versões) no banco.
     * Idempotente. Retorna um resumo [marcas, bases, modelos, falhas].
     * $limpar = true trunca o catálogo antes (requer recriar IDs).
     */
    function fipe_sincronizar(PDO $conn, bool $limpar = false, ?callable $progresso = null): array
    {
        $log = function (string $msg) use ($progresso) {
            if ($progresso) {
                $progresso($msg);
            }
        };

        fipe_garantir_schema($conn);

        // Aliases padrão de marca
        $stAlias = $conn->prepare("INSERT IGNORE INTO fipe_marcas_alias (nome_fipe, nome_exibicao) VALUES (:n, :e)");
        foreach (fipe_aliases_marca() as $nomeFipe => $exibicao) {
            $stAlias->execute([':n' => $nomeFipe, ':e' => $exibicao]);
        }

        if ($limpar) {
            $conn->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['fipe_precos', 'fipe_modelos', 'fipe_modelos_base', 'fipe_marcas'] as $tabela) {
                $conn->exec("TRUNCATE TABLE $tabela");
            }
            $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $aliases = [];
        foreach ($conn->query("SELECT nome_fipe, nome_exibicao FROM fipe_marcas_alias")->fetchAll() as $linha) {
            $aliases[$linha['nome_fipe']] = $linha['nome_exibicao'];
        }

        $log('Buscando marcas da FIPE...');
        $marcas = fipe_http_json(fipe_base_url() . '/carros/marcas');
        if (!$marcas) {
            throw new RuntimeException('Não foi possível obter as marcas da FIPE. Verifique a conexão.');
        }

        $stMarca = $conn->prepare("INSERT INTO fipe_marcas (fipe_codigo, nome, nome_exibicao) VALUES (:cod, :nome, :exib)
            ON DUPLICATE KEY UPDATE nome = VALUES(nome), nome_exibicao = VALUES(nome_exibicao), id = LAST_INSERT_ID(id)");
        $stBase = $conn->prepare("INSERT INTO fipe_modelos_base (marca_id, nome_base) VALUES (:marca, :base)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
        $stModelo = $conn->prepare("INSERT INTO fipe_modelos (base_id, fipe_codigo, nome_versao) VALUES (:base, :cod, :nome)
            ON DUPLICATE KEY UPDATE nome_versao = VALUES(nome_versao)");

        $falhas = [];
        $conn->beginTransaction();
        try {
            foreach ($marcas as $marca) {
                $codigo = (string) ($marca['codigo'] ?? '');
                $nome = trim((string) ($marca['nome'] ?? ''));
                if ($codigo === '' || $nome === '') {
                    continue;
                }
                $exibicao = $aliases[$nome] ?? $nome;

                $stMarca->execute([':cod' => $codigo, ':nome' => $nome, ':exib' => $exibicao]);
                $marcaId = (int) $conn->lastInsertId();

                $dados = fipe_http_json(fipe_base_url() . '/carros/marcas/' . rawurlencode($codigo) . '/modelos');
                if (!$dados || empty($dados['modelos'])) {
                    $falhas[] = $nome;
                    continue;
                }

                foreach ($dados['modelos'] as $modelo) {
                    $modeloCodigo = (string) ($modelo['codigo'] ?? '');
                    $modeloNome = trim((string) ($modelo['nome'] ?? ''));
                    if ($modeloCodigo === '' || $modeloNome === '') {
                        continue;
                    }

                    $base = fipe_nome_base($modeloNome);
                    $stBase->execute([':marca' => $marcaId, ':base' => $base]);
                    $baseId = (int) $conn->lastInsertId();

                    $stModelo->execute([':base' => $baseId, ':cod' => $modeloCodigo, ':nome' => mb_substr($modeloNome, 0, 150)]);
                }

                $log("  [{$codigo}] {$exibicao}: " . count($dados['modelos']) . " modelos");
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return [
            'marcas' => (int) $conn->query('SELECT COUNT(*) FROM fipe_marcas')->fetchColumn(),
            'bases' => (int) $conn->query('SELECT COUNT(*) FROM fipe_modelos_base')->fetchColumn(),
            'modelos' => (int) $conn->query('SELECT COUNT(*) FROM fipe_modelos')->fetchColumn(),
            'falhas' => $falhas,
        ];
    }
}
