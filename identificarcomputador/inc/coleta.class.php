<?php

/**
 * Recebe o JSON enviado pela maquina, limpa e grava.
 * Atualiza o computador existente (reconhecido pela placa-mae/BIOS/hostname) ou cria um novo,
 * e guarda cada execucao no historico de coletas.
 */
class PluginIdentificarcomputadorColeta extends CommonDBTM
{
    public const TABELA_PC      = 'glpi_plugin_identificarcomputador_computadores';
    public const TABELA_COLETAS = 'glpi_plugin_identificarcomputador_coletas';

    /**
     * Processa um envio ja autorizado (token valido).
     * @return array ['success'=>bool, 'message'=>string, 'computador'=>string, 'id'=>int]
     */
    public static function processar(array $dados, int $users_id, string $ip): array
    {
        global $DB;

        $hostname = self::texto($dados['hostname'] ?? '', 255);
        $uuid     = self::texto($dados['uuid'] ?? '', 255);
        $serialB  = self::texto($dados['serial_maquina'] ?? '', 255);

        if ($hostname === '' && $uuid === '' && $serialB === '') {
            return ['success' => false, 'message' => 'Dados insuficientes para identificar a maquina.'];
        }

        // Resumo gravado em colunas proprias (o resto fica no dados_json)
        $resumo = [
            'uuid'                 => $uuid,
            'serial_bios'          => $serialB,
            'hostname'             => $hostname,
            'dominio'              => self::texto($dados['dominio'] ?? '', 255),
            'usuario_logado'       => self::texto($dados['usuario_logado'] ?? '', 255),
            'so'                   => self::texto($dados['so'] ?? '', 255),
            'so_versao'            => self::texto($dados['so_versao'] ?? '', 100),
            'so_build'             => self::texto($dados['so_build'] ?? '', 100),
            'fabricante'           => self::texto($dados['fabricante'] ?? '', 255),
            'modelo'               => self::texto($dados['modelo'] ?? '', 255),
            'serial_maquina'       => $serialB,
            'placa_mae'            => self::texto($dados['placa_mae'] ?? '', 255),
            'processador'          => self::texto($dados['processador'] ?? '', 255),
            'ram_total'            => (int) ($dados['ram_total_bytes'] ?? 0),
            'armazenamento_total'  => (int) ($dados['armazenamento_total_bytes'] ?? 0),
            'placa_video'          => self::texto($dados['placa_video'] ?? '', 2000),
            'antivirus'            => self::texto($dados['antivirus'] ?? '', 255),
            'antivirus_estado'     => self::texto($dados['antivirus_estado'] ?? '', 100),
            'firewall'             => self::texto($dados['firewall'] ?? '', 100),
            'acesso_remoto'        => self::texto($dados['acesso_remoto'] ?? '', 2000),
            'ips'                  => self::texto($dados['ips'] ?? '', 2000),
            'macs'                 => self::texto($dados['macs'] ?? '', 2000),
            'dados_json'           => json_encode($dados, JSON_UNESCAPED_UNICODE),
        ];

        // Procura a maquina ja cadastrada
        $existente = null;
        if ($uuid !== '' && !self::uuidGenerico($uuid)) {
            $existente = $DB->request(['FROM' => self::TABELA_PC, 'WHERE' => ['uuid' => $uuid], 'LIMIT' => 1])->current();
        }
        if (!$existente && $serialB !== '') {
            $existente = $DB->request(['FROM' => self::TABELA_PC, 'WHERE' => ['serial_bios' => $serialB, 'hostname' => $hostname], 'LIMIT' => 1])->current();
        }
        if (!$existente && $hostname !== '') {
            $existente = $DB->request(['FROM' => self::TABELA_PC, 'WHERE' => ['hostname' => $hostname, 'uuid' => ''], 'LIMIT' => 1])->current();
        }

        if ($existente) {
            $id = (int) $existente['id'];
            $resumo['qtd_coletas'] = (int) $existente['qtd_coletas'] + 1;
            $DB->update(self::TABELA_PC, $resumo, ['id' => $id]);
        } else {
            $resumo['users_id_baixou'] = $users_id;
            $resumo['qtd_coletas']     = 1;
            $DB->insert(self::TABELA_PC, $resumo);
            $id = (int) $DB->insertId();
        }

        // Historico desta execucao
        $DB->insert(self::TABELA_COLETAS, [
            'computadores_id' => $id,
            'dados_json'      => json_encode($dados, JSON_UNESCAPED_UNICODE),
            'ip_origem'       => substr($ip, 0, 100),
        ]);

        return ['success' => true, 'message' => 'Dados recebidos.', 'computador' => $hostname !== '' ? $hostname : ('#' . $id), 'id' => $id];
    }

    private static function texto($v, int $max): string
    {
        if (is_array($v)) {
            $v = implode(', ', array_map('strval', $v));
        }
        $v = trim((string) $v);
        return mb_substr($v, 0, $max);
    }

    /** UUIDs que alguns fabricantes repetem em todas as maquinas; nao servem para identificar. */
    private static function uuidGenerico(string $uuid): bool
    {
        $u = strtoupper(trim($uuid));
        $ruins = ['', '00000000-0000-0000-0000-000000000000', 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', '03000200-0400-0500-0006-000700080009'];
        return in_array($u, $ruins, true);
    }
}
