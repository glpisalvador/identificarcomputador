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

    // ----------------------------------------------------------------- resumo legivel

    /** Monta os pares principais (label => valor) do resumo de um computador. */
    public static function resumoPares(array $det): array
    {
        $d = $det['dados'];
        $g = static fn($b): string => self::formatarBytes($b);

        // Processador + socket
        $proc = trim((string) ($d['processador'] ?? ''));
        $sock = (string) ($d['processadores'][0]['socket'] ?? '');
        if ($sock !== '') { $proc = trim($proc . ' · Socket ' . $sock); }

        // Memoria + slots + modelo/barramento/tipo
        $ram = isset($d['ram_total_gb']) ? ($d['ram_total_gb'] . ' GB') : $g($det['resumo']['ram_total'] ?? 0);
        if (isset($d['ram_slots_usados'])) {
            $tot = (int) ($d['ram_slots_total'] ?? $d['ram_slots_usados']);
            $ram .= ' · ' . (int) $d['ram_slots_usados'] . ' de ' . $tot . ' slot(s)';
        }
        $mem = (array) ($d['memoria'] ?? []);
        if ($mem) {
            $descMem = [];
            foreach ($mem as $m) {
                $p = trim((string) ($m['tipo'] ?? '') . ' ' . (isset($m['capacidade_gb']) ? $m['capacidade_gb'] . 'GB' : ''));
                $bar = (int) ($m['barramento_mhz'] ?? $m['velocidade_mhz'] ?? 0);
                if ($bar > 0) { $p .= ' ' . $bar . 'MHz'; }
                if (!empty($m['modelo'])) { $p .= ' ' . $m['modelo']; }
                $p = trim($p);
                if ($p !== '') { $descMem[] = $p; }
            }
            if ($descMem) { $ram .= ' · ' . implode(' | ', $descMem); }
        }

        // Armazenamento + unidades + modelos
        $discos = (array) ($d['discos'] ?? []);
        $arm = $g($d['armazenamento_total_bytes'] ?? ($det['resumo']['armazenamento_total'] ?? 0));
        if ($discos) {
            $arm = count($discos) . ' unidade(s) · ' . $arm;
            $descD = [];
            foreach ($discos as $disk) {
                $p = trim((string) ($disk['modelo'] ?? ''));
                if (!empty($disk['tamanho_gb'])) { $p .= ' ' . $disk['tamanho_gb'] . 'GB'; }
                if (!empty($disk['tipo'])) { $p .= ' ' . $disk['tipo']; }
                $p = trim($p);
                if ($p !== '') { $descD[] = $p; }
            }
            if ($descD) { $arm .= ' · ' . implode(' | ', $descD); }
        }

        // Video + GDDR
        $video = '';
        foreach ((array) ($d['placas_video'] ?? []) as $pv) {
            $s = (string) ($pv['nome'] ?? '');
            $gbv = (float) ($pv['memoria_gb'] ?? 0);
            if ($gbv > 0) { $s .= ' · ' . $gbv . ' GB' . (!empty($pv['tipo_memoria']) ? ' ' . $pv['tipo_memoria'] : ''); }
            if ($s !== '') { $video = $video === '' ? $s : $video . '; ' . $s; }
        }
        if ($video === '') { $video = (string) ($d['placa_video'] ?? ''); }

        // Rede: descricao + conexao + capacidade
        $placaRede = trim((string) ($d['placa_rede_principal'] ?? $d['placa_rede'] ?? ''));
        if ($placaRede === '' && !empty($d['adaptadores_rede'][0]['descricao'])) { $placaRede = (string) $d['adaptadores_rede'][0]['descricao']; }
        $conex = trim((string) ($d['conexao_principal'] ?? ''));
        $cap = trim((string) ($d['rede_capacidade'] ?? ''));
        $redeTipo = trim($conex . ($cap !== '' ? ' · ' . $cap : ''));

        // Som
        $placaSom = $d['placas_som'] ?? '';
        if (is_array($placaSom)) { $placaSom = implode('; ', $placaSom); }

        // Sistema + build
        $sis = trim((string) ($d['so'] ?? '') . ' ' . (string) ($d['so_arquitetura'] ?? ''));
        $build = (string) ($d['so_build_completo'] ?? $d['so_build'] ?? '');
        $rel = (string) ($d['so_release'] ?? '');
        if ($build !== '') { $sis .= ' · build ' . $build . ($rel !== '' ? ' (' . $rel . ')' : ''); }

        // Antivirus
        $avs = (array) ($d['antivirus_lista'] ?? []);
        $av = [];
        foreach ($avs as $a) {
            $p = (string) ($a['nome'] ?? '');
            if (!empty($a['estado'])) { $p .= ' (' . $a['estado'] . ')'; }
            if (trim($p) !== '') { $av[] = $p; }
        }
        $avLinha = $av ? implode('; ', $av) : (string) ($d['antivirus'] ?? '');

        // MAC e IP principais
        $macP = trim((string) ($d['mac_principal'] ?? ''));
        if ($macP === '') { $macP = (string) ($d['adaptadores_rede'][0]['mac'] ?? $det['resumo']['macs'] ?? ''); }
        $ipP = trim((string) ($d['ip_principal'] ?? ''));
        if ($ipP === '') { $ipP = (string) ($d['ips_detalhe'][0]['ipv4'] ?? $det['resumo']['ips'] ?? ''); }

        // Licenca Windows + chave
        $lw = $d['licencas']['windows'] ?? null;
        $winAval = (string) ($d['windows_licenca'] ?? '');
        if (is_array($lw)) {
            $chave = trim((string) ($lw['chave_produto'] ?? ''));
            if ($chave === '') { $chave = trim((string) ($lw['chave_parcial'] ?? '')); }
            if ($chave !== '') { $winAval .= ' · ' . $chave; }
        }

        return [
            'Hostname'            => $d['hostname'] ?? $det['hostname'],
            'Usuário logado'      => $d['usuario_logado'] ?? '',
            'Tipo de usuário'     => $d['usuario_tipo'] ?? '',
            'Domínio'             => ($d['dominio'] ?? '') . (isset($d['parte_de_dominio']) && !$d['parte_de_dominio'] ? ' (grupo de trabalho)' : ''),
            'Tipo'                => $d['tipo'] ?? '',
            'Sistema'             => $sis,
            'Instalado em'        => $d['instalado_em'] ?? '',
            'Placa-mãe'           => $d['placa_mae'] ?? '',
            'Processador'         => $proc,
            'Memória RAM'         => $ram,
            'Armazenamento'       => $arm,
            'Placa de vídeo'      => $video,
            'Placa de rede'       => $placaRede,
            'Conexão de rede'     => $redeTipo,
            'Placa de som'        => $placaSom,
            'Antivírus'           => $avLinha,
            'Endereço MAC'        => $macP,
            'IP'                  => $ipP,
            'Licença do Windows'  => $winAval,
            'Licença do Office'   => $d['office_licenca'] ?? '',
        ];
    }

    /** Resumo em HTML para incluir num acompanhamento (followup). */
    public static function resumoHtml(int $id): string
    {
        $det = self::getDetalhe($id);
        if ($det === null) { return ''; }
        $pares = self::resumoPares($det);
        $esc = static fn($x): string => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');

        $h = '<h3>Resumo do computador: ' . $esc($det['hostname']) . '</h3>';
        $h .= '<table border="1" cellpadding="4" cellspacing="0">';
        foreach ($pares as $k => $vv) {
            $vv = is_array($vv) ? implode(', ', array_map('strval', $vv)) : (string) $vv;
            if (trim($vv) === '') { $vv = '-'; }
            $h .= '<tr><td><b>' . $esc($k) . '</b></td><td>' . $esc($vv) . '</td></tr>';
        }
        $h .= '</table>';

        // Acesso remoto, se houver
        $rem = (array) ($det['dados']['acesso_remoto_lista'] ?? []);
        if ($rem) {
            $h .= '<p><b>Acesso remoto:</b> ';
            $itens = [];
            foreach ($rem as $r) {
                $itens[] = $esc(($r['programa'] ?? '') . ' - ID ' . ($r['id'] ?? ''));
            }
            $h .= implode(' | ', $itens) . '</p>';
        }

        $h .= '<p><i>Coletado em ' . $esc($det['dados']['coletado_em'] ?? '') . ' pelo plugin Identificar Computador.</i></p>';
        return $h;
    }

    // ----------------------------------------------------------------- enviar para ITIL

    /** Cria um acompanhamento com o resumo do computador num ticket, problema ou mudanca. */
    public static function enviarParaItil(int $id, string $tipo, int $itemId): array
    {
        $mapa = ['Ticket' => 'Ticket', 'Problem' => 'Problem', 'Change' => 'Change'];
        if (!isset($mapa[$tipo])) {
            return ['success' => false, 'message' => 'Tipo de item inválido.'];
        }
        $classe = $mapa[$tipo];
        if (!class_exists($classe)) {
            return ['success' => false, 'message' => 'Tipo de item indisponível.'];
        }
        $item = new $classe();
        if (!$item->getFromDB($itemId)) {
            $nomes = ['Ticket' => 'Chamado', 'Problem' => 'Problema', 'Change' => 'Mudança'];
            return ['success' => false, 'message' => $nomes[$tipo] . ' nº ' . $itemId . ' não encontrado.'];
        }
        if (!$item->canViewItem()) {
            return ['success' => false, 'message' => 'Você não tem acesso a esse item.'];
        }
        $html = self::resumoHtml($id);
        if ($html === '') {
            return ['success' => false, 'message' => 'Computador não encontrado.'];
        }
        $fup = new ITILFollowup();
        $novo = $fup->add([
            'itemtype'   => $classe,
            'items_id'   => $itemId,
            'content'    => $html,
            'is_private' => 0,
            'users_id'   => Session::getLoginUserID(),
        ]);
        if (!$novo) {
            return ['success' => false, 'message' => 'Não foi possível adicionar o acompanhamento (verifique suas permissões no item).'];
        }
        $nomes = ['Ticket' => 'chamado', 'Problem' => 'problema', 'Change' => 'mudança'];
        return ['success' => true, 'message' => 'Resumo enviado ao ' . $nomes[$tipo] . ' nº ' . $itemId . '.'];
    }

    // ----------------------------------------------------------------- converter em ativo nativo

    /** Entidades disponiveis para o usuario (id => completename). */
    public static function entidadesDisponiveis(): array
    {
        global $DB;
        $out = [];
        $q = ['SELECT' => ['id', 'completename'], 'FROM' => 'glpi_entities', 'ORDER' => 'completename'];
        $ids = $_SESSION['glpiactiveentities'] ?? [];
        if (is_array($ids) && $ids && !in_array('all', $ids, true)) {
            $q['WHERE'] = ['id' => array_values(array_map('intval', $ids))];
        }
        foreach ($DB->request($q) as $r) {
            $out[(int) $r['id']] = (string) $r['completename'];
        }
        return $out;
    }

    /** Converte o registro do plugin num Computador (ativo nativo) na entidade escolhida. */
    public static function converterParaAtivo(int $id, int $entidade): array
    {
        if (!Session::haveRight('computer', CREATE)) {
            return ['success' => false, 'message' => 'Você não tem permissão para criar computadores nos ativos.'];
        }
        $det = self::getDetalhe($id);
        if ($det === null) {
            return ['success' => false, 'message' => 'Computador não encontrado.'];
        }
        $d = $det['dados'];

        $nome = trim((string) ($d['hostname'] ?? $det['hostname']));
        if ($nome === '') { $nome = 'Computador #' . $id; }

        $input = [
            'name'        => $nome,
            'entities_id' => $entidade,
            'serial'      => (string) ($d['serial_maquina'] ?? ''),
            'uuid'        => (string) ($d['uuid'] ?? ''),
            'contact'     => (string) ($d['usuario_logado'] ?? ''),
            'comment'     => "Importado do plugin Identificar Computador em " . date('d/m/Y H:i') . ".",
        ];
        try { if (!empty($d['fabricante'])) { $input['manufacturers_id']  = (int) Dropdown::importExternal('Manufacturer', (string) $d['fabricante']); } } catch (\Throwable $ex) {}
        try { if (!empty($d['modelo']))     { $input['computermodels_id'] = (int) Dropdown::importExternal('ComputerModel', (string) $d['modelo']); } } catch (\Throwable $ex) {}
        try { if (!empty($d['tipo']))       { $input['computertypes_id']  = (int) Dropdown::importExternal('ComputerType', (string) $d['tipo']); } } catch (\Throwable $ex) {}

        $computer = new Computer();
        $novo = $computer->add($input);
        if (!$novo) {
            return ['success' => false, 'message' => 'Não foi possível criar o computador nos ativos.'];
        }
        $novo = (int) $novo;

        // Sistema operacional
        try {
            if (!empty($d['so']) && class_exists('Item_OperatingSystem')) {
                $ios = new Item_OperatingSystem();
                $ios->add([
                    'itemtype'                        => 'Computer',
                    'items_id'                        => $novo,
                    'operatingsystems_id'             => (int) Dropdown::importExternal('OperatingSystem', (string) $d['so']),
                    'operatingsystemversions_id'      => !empty($d['so_versao']) ? (int) Dropdown::importExternal('OperatingSystemVersion', (string) $d['so_versao']) : 0,
                    'operatingsystemarchitectures_id' => !empty($d['so_arquitetura']) ? (int) Dropdown::importExternal('OperatingSystemArchitecture', (string) $d['so_arquitetura']) : 0,
                ]);
            }
        } catch (\Throwable $ex) {}

        // Processador(es)
        try {
            if (class_exists('DeviceProcessor') && class_exists('Item_DeviceProcessor')) {
                foreach ((array) ($d['processadores'] ?? []) as $c) {
                    if (empty($c['nome'])) { continue; }
                    $devId = (int) (new DeviceProcessor())->import([
                        'designation'      => (string) $c['nome'],
                        'frequence'        => (int) ($c['clock_mhz'] ?? 0),
                        'frequence_default'=> (int) ($c['clock_mhz'] ?? 0),
                        'nbcores_default'  => (int) ($c['nucleos'] ?? 0),
                        'nbthreads_default'=> (int) ($c['logicos'] ?? 0),
                        'entities_id'      => 0,
                    ]);
                    if ($devId > 0) {
                        (new Item_DeviceProcessor())->add([
                            'itemtype'             => 'Computer',
                            'items_id'             => $novo,
                            'deviceprocessors_id'  => $devId,
                            'frequency'            => (int) ($c['clock_mhz'] ?? 0),
                            'nbcores'              => (int) ($c['nucleos'] ?? 0),
                            'nbthreads'            => (int) ($c['logicos'] ?? 0),
                            'entities_id'          => $entidade,
                        ]);
                    }
                }
            }
        } catch (\Throwable $ex) {}

        // Memorias
        try {
            if (class_exists('DeviceMemory') && class_exists('Item_DeviceMemory')) {
                foreach ((array) ($d['memoria'] ?? []) as $m) {
                    $sizeMb = (int) round((float) ($m['capacidade_gb'] ?? 0) * 1024);
                    if ($sizeMb <= 0) { continue; }
                    $devId = (int) (new DeviceMemory())->import([
                        'designation'  => (string) ($m['modelo'] ?? 'Memória'),
                        'frequence'    => (int) ($m['barramento_mhz'] ?? $m['velocidade_mhz'] ?? 0),
                        'size_default' => $sizeMb,
                        'entities_id'  => 0,
                    ]);
                    if ($devId > 0) {
                        (new Item_DeviceMemory())->add([
                            'itemtype'         => 'Computer',
                            'items_id'         => $novo,
                            'devicememories_id'=> $devId,
                            'size'             => $sizeMb,
                            'entities_id'      => $entidade,
                        ]);
                    }
                }
            }
        } catch (\Throwable $ex) {}

        // Discos
        try {
            if (class_exists('DeviceHardDrive') && class_exists('Item_DeviceHardDrive')) {
                foreach ((array) ($d['discos'] ?? []) as $disk) {
                    if (empty($disk['modelo'])) { continue; }
                    $capMb = (int) round((float) ($disk['tamanho_gb'] ?? 0) * 1024);
                    $devId = (int) (new DeviceHardDrive())->import([
                        'designation'      => (string) $disk['modelo'],
                        'capacity_default' => $capMb,
                        'entities_id'      => 0,
                    ]);
                    if ($devId > 0) {
                        (new Item_DeviceHardDrive())->add([
                            'itemtype'           => 'Computer',
                            'items_id'           => $novo,
                            'deviceharddrives_id'=> $devId,
                            'capacity'           => $capMb,
                            'entities_id'        => $entidade,
                        ]);
                    }
                }
            }
        } catch (\Throwable $ex) {}

        // Porta de rede com o MAC principal
        try {
            $mac = trim((string) ($d['mac_principal'] ?? ($d['adaptadores_rede'][0]['mac'] ?? '')));
            if ($mac !== '' && class_exists('NetworkPort')) {
                (new NetworkPort())->add([
                    'itemtype'           => 'Computer',
                    'items_id'           => $novo,
                    'entities_id'        => $entidade,
                    'name'               => (string) ($d['placa_rede_principal'] ?? 'LAN'),
                    'mac'                => $mac,
                    'instantiation_type' => 'NetworkPortEthernet',
                ]);
            }
        } catch (\Throwable $ex) {}

        return [
            'success' => true,
            'message' => 'Computador criado nos ativos do GLPI (nº ' . $novo . ').',
            'id'      => $novo,
            'url'     => Computer::getFormURLWithID($novo),
        ];
    }
}
