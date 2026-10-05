<?php

/**
 * Consultas do painel: lista com filtros, cartoes de resumo, dados dos graficos,
 * pagina de detalhe (com historico) e exclusao.
 */
class PluginIdentificarcomputadorComputador extends CommonDBTM
{
    public const TABELA         = 'glpi_plugin_identificarcomputador_computadores';
    public const TABELA_COLETAS = 'glpi_plugin_identificarcomputador_coletas';

    public static function getTypeName($nb = 0): string
    {
        return _n('Computador', 'Computadores', $nb, 'identificarcomputador');
    }

    public static function canView(): bool
    {
        return PluginIdentificarcomputadorConfig::podeVer();
    }

    public static function canDelete(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    /** Monta o WHERE a partir dos filtros recebidos. */
    private static function where(array $f): array
    {
        $where = [];

        $busca = trim((string) ($f['busca'] ?? ''));
        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $where[] = ['OR' => [
                'hostname'       => ['LIKE', $like],
                'usuario_logado' => ['LIKE', $like],
                'modelo'         => ['LIKE', $like],
                'fabricante'     => ['LIKE', $like],
                'ips'            => ['LIKE', $like],
                'macs'           => ['LIKE', $like],
                'serial_maquina' => ['LIKE', $like],
                'dominio'        => ['LIKE', $like],
            ]];
        }

        foreach (['so' => 'so', 'fabricante' => 'fabricante', 'antivirus' => 'antivirus', 'firewall' => 'firewall', 'dominio' => 'dominio'] as $campo => $coluna) {
            $vals = (array) ($f[$campo] ?? []);
            $vals = array_values(array_filter(array_map('strval', $vals), fn($x) => $x !== ''));
            if ($vals) {
                $where[$coluna] = $vals;
            }
        }

        if (!empty($f['com_acesso_remoto'])) {
            // IS NOT NULL E <> '' (nao usar NOT IN ('', NULL): o NULL na lista anula tudo)
            $where[] = ['NOT' => ['acesso_remoto' => null]];
            $where[] = ['acesso_remoto' => ['<>', '']];
        }

        $de  = trim((string) ($f['data_de'] ?? ''));
        $ate = trim((string) ($f['data_ate'] ?? ''));
        if ($de !== '')  { $where['date_mod'][] = ['>=', $de . ' 00:00:00']; }
        if ($ate !== '') { $where['date_mod'][] = ['<=', $ate . ' 23:59:59']; }

        return $where;
    }

    /** Lista para a tabela do painel (uma linha por computador). */
    public static function listar(array $filtros = []): array
    {
        global $DB;
        $out = [];
        $req = $DB->request([
            'SELECT' => ['id', 'hostname', 'usuario_logado', 'dominio', 'so', 'fabricante', 'modelo',
                         'processador', 'ram_total', 'armazenamento_total', 'antivirus', 'antivirus_estado',
                         'firewall', 'acesso_remoto', 'ips', 'macs', 'qtd_coletas', 'date_creation', 'date_mod'],
            'FROM'   => self::TABELA,
            'WHERE'  => self::where($filtros),
            'ORDER'  => 'date_mod DESC',
            'LIMIT'  => 5000,
        ]);
        foreach ($req as $r) {
            $out[] = [
                'id'             => (int) $r['id'],
                'hostname'       => (string) $r['hostname'],
                'usuario'        => (string) $r['usuario_logado'],
                'dominio'        => (string) $r['dominio'],
                'so'             => (string) $r['so'],
                'fabricante'     => (string) $r['fabricante'],
                'modelo'         => (string) $r['modelo'],
                'processador'    => (string) $r['processador'],
                'ram_gb'         => round(((int) $r['ram_total']) / (1024 ** 3), 1),
                'disco_gb'       => round(((int) $r['armazenamento_total']) / (1024 ** 3), 0),
                'antivirus'      => (string) $r['antivirus'],
                'antivirus_estado' => (string) $r['antivirus_estado'],
                'firewall'       => (string) $r['firewall'],
                'acesso_remoto'  => (string) $r['acesso_remoto'],
                'ips'            => (string) $r['ips'],
                'macs'           => (string) $r['macs'],
                'coletas'        => (int) $r['qtd_coletas'],
                'primeira'       => (string) $r['date_creation'],
                'ultima'         => (string) $r['date_mod'],
            ];
        }
        return $out;
    }

    /** Valores distintos para preencher os filtros. */
    public static function opcoesFiltros(): array
    {
        global $DB;
        $campos = ['so', 'fabricante', 'antivirus', 'firewall', 'dominio'];
        $out = [];
        foreach ($campos as $c) {
            $vals = [];
            foreach ($DB->request(['SELECT' => [$c], 'DISTINCT' => true, 'FROM' => self::TABELA, 'WHERE' => ['NOT' => [$c => '']], 'ORDER' => $c]) as $row) {
                if (trim((string) $row[$c]) !== '') {
                    $vals[] = (string) $row[$c];
                }
            }
            $out[$c] = $vals;
        }
        return $out;
    }

    /** Cartoes de resumo do topo do painel. */
    public static function resumoCards(): array
    {
        global $DB;
        $total = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA])->current()['total'] ?? 0);
        $comRemoto = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA, 'WHERE' => [['NOT' => ['acesso_remoto' => null]], ['acesso_remoto' => ['<>', '']]]])->current()['total'] ?? 0);
        $semAntivirus = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA, 'WHERE' => ['antivirus' => '']])->current()['total'] ?? 0);
        $hoje = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA, 'WHERE' => ['date_mod' => ['>=', date('Y-m-d 00:00:00')]]])->current()['total'] ?? 0);
        return [
            'total'         => $total,
            'com_remoto'    => $comRemoto,
            'sem_antivirus' => $semAntivirus,
            'hoje'          => $hoje,
        ];
    }

    /** Dados para os graficos (contagem por valor). */
    public static function dadosGraficos(): array
    {
        return [
            'por_so'         => self::contarPor('so'),
            'por_fabricante' => self::contarPor('fabricante'),
            'por_antivirus'  => self::contarPor('antivirus'),
            'por_firewall'   => self::contarPor('firewall'),
        ];
    }

    private static function contarPor(string $coluna): array
    {
        global $DB;
        $out = [];
        $req = $DB->request([
            'SELECT' => [$coluna, new \Glpi\DBAL\QueryExpression('COUNT(*) AS total')],
            'FROM'   => self::TABELA,
            'GROUPBY'=> $coluna,
            'ORDER'  => 'total DESC',
        ]);
        foreach ($req as $r) {
            $rotulo = trim((string) $r[$coluna]);
            if ($rotulo === '') { $rotulo = '(nao informado)'; }
            $out[] = ['rotulo' => $rotulo, 'total' => (int) $r['total']];
        }
        return $out;
    }

    /** Detalhe de um computador: campos + dados_json + historico. */
    public static function getDetalhe(int $id): ?array
    {
        global $DB;
        $r = $DB->request(['FROM' => self::TABELA, 'WHERE' => ['id' => $id], 'LIMIT' => 1])->current();
        if (!$r) {
            return null;
        }
        $dados = json_decode((string) $r['dados_json'], true);
        if (!is_array($dados)) { $dados = []; }

        $historico = [];
        foreach ($DB->request(['SELECT' => ['id', 'ip_origem', 'date_creation'], 'FROM' => self::TABELA_COLETAS, 'WHERE' => ['computadores_id' => $id], 'ORDER' => 'date_creation DESC', 'LIMIT' => 100]) as $h) {
            $historico[] = [
                'id'    => (int) $h['id'],
                'ip'    => (string) $h['ip_origem'],
                'data'  => Html::convDateTime($h['date_creation']),
            ];
        }

        return [
            'id'        => $id,
            'hostname'  => (string) $r['hostname'],
            'resumo'    => $r,
            'dados'     => $dados,
            'historico' => $historico,
        ];
    }

    /** Dados de uma coleta especifica do historico. */
    public static function getColeta(int $coletaId): ?array
    {
        global $DB;
        $r = $DB->request(['FROM' => self::TABELA_COLETAS, 'WHERE' => ['id' => $coletaId], 'LIMIT' => 1])->current();
        if (!$r) { return null; }
        $dados = json_decode((string) $r['dados_json'], true);
        return is_array($dados) ? $dados : [];
    }

    public static function excluir(int $id): bool
    {
        global $DB;
        $DB->delete(self::TABELA_COLETAS, ['computadores_id' => $id]);
        return (bool) $DB->delete(self::TABELA, ['id' => $id]);
    }

    public static function formatarBytes($bytes): string
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) { return '-'; }
        $un = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($un) - 1));
        return round($bytes / (1024 ** $i), $i >= 3 ? 1 : 0) . ' ' . $un[$i];
    }
}
