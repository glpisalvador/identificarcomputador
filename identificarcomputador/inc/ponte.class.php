<?php

/**
 * Ponte de execucao remota (temporaria, enquanto a janela do script fica aberta).
 *
 * Fluxo:
 *   - O agente (PowerShell elevado) registra uma sessao pelo token e faz polling.
 *   - Pelo GLPI, um usuario autorizado envia arquivos (biblioteca da sessao), escolhe
 *     qual executar (cria um job) e acompanha o resultado.
 *   - O agente baixa o arquivo do job, executa como administrador, devolve a saida.
 *   - Cada execucao fica no log (quem enviou, arquivo, formato, duracao, data/hora, PC).
 *
 * Nada fica instalado na maquina: a ponte morre ao fechar a janela ou em 30 min.
 */
class PluginIdentificarcomputadorPonte extends CommonDBTM
{
    public const T_SESSOES  = 'glpi_plugin_identificarcomputador_ponte_sessoes';
    public const T_ARQUIVOS = 'glpi_plugin_identificarcomputador_ponte_arquivos';
    public const T_JOBS     = 'glpi_plugin_identificarcomputador_ponte_jobs';

    /** Segundos desde o ultimo contato para a maquina ser considerada online. */
    public const ONLINE_SEG = 25;
    /** Limite de saida guardada por execucao (2 MB de texto). */
    public const MAX_SAIDA = 2000000;

    // ================================================================ AGENTE

    /** Registra/atualiza a sessao do agente. Retorna o id da sessao ou null se o token for invalido. */
    public static function registrarSessao(string $token, array $info, string $ip): ?int
    {
        global $DB;
        $usersId = PluginIdentificarcomputadorScript::validarToken($token, $ip);
        if ($usersId === null) {
            return null;
        }
        $hostname = mb_substr(trim((string) ($info['hostname'] ?? '')), 0, 255);
        $compId   = self::resolverComputador($hostname, (string) ($info['uuid'] ?? ''));

        // Expiracao alinhada a do token
        $tk = $DB->request(['FROM' => PluginIdentificarcomputadorScript::TABELA_TOKENS, 'WHERE' => ['token' => $token], 'LIMIT' => 1])->current();
        $exp = $tk ? (string) $tk['date_expiracao'] : date('Y-m-d H:i:s', time() + 1800);

        $existe = $DB->request(['FROM' => self::T_SESSOES, 'WHERE' => ['token' => $token], 'LIMIT' => 1])->current();
        $dados = [
            'users_id_dono'  => (int) $usersId,
            'computadores_id'=> $compId,
            'hostname'       => $hostname,
            'ip'             => mb_substr($ip, 0, 100),
            'so'             => mb_substr((string) ($info['so'] ?? ''), 0, 255),
            'usuario_logado' => mb_substr((string) ($info['usuario_logado'] ?? ''), 0, 255),
            'last_seen'      => date('Y-m-d H:i:s'),
            'date_expiracao' => $exp,
            'ativo'          => 1,
        ];
        if ($existe) {
            $DB->update(self::T_SESSOES, $dados, ['id' => (int) $existe['id']]);
            return (int) $existe['id'];
        }
        $dados['token'] = $token;
        $DB->insert(self::T_SESSOES, $dados);
        return (int) $DB->insertId();
    }

    private static function resolverComputador(string $hostname, string $uuid): int
    {
        global $DB;
        $tab = PluginIdentificarcomputadorComputador::TABELA;
        if ($uuid !== '') {
            $r = $DB->request(['SELECT' => ['id'], 'FROM' => $tab, 'WHERE' => ['uuid' => $uuid], 'LIMIT' => 1])->current();
            if ($r) { return (int) $r['id']; }
        }
        if ($hostname !== '') {
            $r = $DB->request(['SELECT' => ['id'], 'FROM' => $tab, 'WHERE' => ['hostname' => $hostname], 'ORDER' => 'date_mod DESC', 'LIMIT' => 1])->current();
            if ($r) { return (int) $r['id']; }
        }
        return 0;
    }

    /**
     * Jobs que o agente deve executar agora. Marca cada um como "executando" na entrega,
     * para nao rodar duas vezes. Retorna [{id, nome, formato}].
     */
    public static function jobsParaAgente(int $sessoesId): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['sessoes_id' => $sessoesId, 'status' => 'pendente'], 'ORDER' => 'id ASC']) as $j) {
            $DB->update(self::T_JOBS, ['status' => 'executando', 'date_inicio' => date('Y-m-d H:i:s')], ['id' => (int) $j['id']]);
            $out[] = ['id' => (int) $j['id'], 'nome' => $j['nome'], 'formato' => $j['formato']];
        }
        return $out;
    }

    /** Caminho do arquivo de um job, validando que ele pertence ao token. Retorna [caminho, nome] ou null. */
    public static function arquivoDoJob(int $jobId, string $token): ?array
    {
        global $DB;
        $j = $DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['id' => $jobId], 'LIMIT' => 1])->current();
        if (!$j) { return null; }
        $s = $DB->request(['FROM' => self::T_SESSOES, 'WHERE' => ['id' => (int) $j['sessoes_id']], 'LIMIT' => 1])->current();
        if (!$s || $s['token'] !== $token) { return null; }
        $a = $DB->request(['FROM' => self::T_ARQUIVOS, 'WHERE' => ['id' => (int) $j['arquivos_id']], 'LIMIT' => 1])->current();
        if (!$a || !is_file($a['caminho'])) { return null; }
        return ['caminho' => (string) $a['caminho'], 'nome' => (string) $a['nome']];
    }

    /** O agente devolve o resultado de um job. */
    public static function registrarResultado(int $jobId, string $token, ?int $exit, string $saida): bool
    {
        global $DB;
        $j = $DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['id' => $jobId], 'LIMIT' => 1])->current();
        if (!$j) { return false; }
        $s = $DB->request(['FROM' => self::T_SESSOES, 'WHERE' => ['id' => (int) $j['sessoes_id']], 'LIMIT' => 1])->current();
        if (!$s || $s['token'] !== $token) { return false; }

        $inicio = $j['date_inicio'] ? strtotime((string) $j['date_inicio']) : time();
        $dur = max(0, time() - $inicio);
        return (bool) $DB->update(self::T_JOBS, [
            'status'      => ($exit === 0 ? 'concluido' : 'erro'),
            'exit_code'   => $exit,
            'saida'       => mb_substr($saida, 0, self::MAX_SAIDA),
            'date_fim'    => date('Y-m-d H:i:s'),
            'duracao_seg' => $dur,
        ], ['id' => $jobId]);
    }

    // ================================================================ GLPI (UI)

    /** Sessoes online (ativas, vistas ha pouco e dentro do prazo). */
    public static function sessoesOnline(): array
    {
        global $DB;
        $limite = date('Y-m-d H:i:s', time() - self::ONLINE_SEG);
        $agora  = date('Y-m-d H:i:s');
        $out = [];
        foreach ($DB->request([
            'FROM'  => self::T_SESSOES,
            'WHERE' => ['ativo' => 1, 'last_seen' => ['>=', $limite], 'date_expiracao' => ['>=', $agora]],
            'ORDER' => 'last_seen DESC',
        ]) as $s) {
            $out[] = [
                'id'              => (int) $s['id'],
                'hostname'        => (string) $s['hostname'],
                'ip'              => (string) $s['ip'],
                'so'              => (string) $s['so'],
                'usuario'         => (string) $s['usuario_logado'],
                'computadores_id' => (int) $s['computadores_id'],
                'expira_em'       => (string) $s['date_expiracao'],
                'segundos_restantes' => max(0, strtotime((string) $s['date_expiracao']) - time()),
            ];
        }
        return $out;
    }

    /** IDs de computadores com sessao online (para o painel marcar). */
    public static function idsComputadoresOnline(): array
    {
        $ids = [];
        foreach (self::sessoesOnline() as $s) {
            if ($s['computadores_id'] > 0) { $ids[] = $s['computadores_id']; }
        }
        return array_values(array_unique($ids));
    }

    private static function sessaoOnline(int $sessoesId): ?array
    {
        foreach (self::sessoesOnline() as $s) {
            if ($s['id'] === $sessoesId) { return $s; }
        }
        return null;
    }

    public static function arquivosDaSessao(int $sessoesId): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => self::T_ARQUIVOS, 'WHERE' => ['sessoes_id' => $sessoesId], 'ORDER' => 'id DESC']) as $a) {
            $out[] = [
                'id'      => (int) $a['id'],
                'nome'    => (string) $a['nome'],
                'formato' => (string) $a['formato'],
                'tamanho' => (int) $a['tamanho'],
            ];
        }
        return $out;
    }

    // ---- Upload em pedacos (para arquivos grandes, ate o limite configurado) ----

    private static function dirTemp(): string
    {
        $d = PluginIdentificarcomputadorConfig::dirScripts() . '/_tmp';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    /** Grava um pedaco do arquivo num temporario identificado por uploadId. */
    public static function gravarChunk(string $uploadId, int $indice, string $bin): array
    {
        if (!preg_match('/^[a-f0-9]{8,64}$/', $uploadId)) {
            return ['success' => false, 'message' => 'Upload invalido.'];
        }
        $tmp = self::dirTemp() . '/' . $uploadId . '.part';
        $max = PluginIdentificarcomputadorConfig::getMaxUploadMb() * 1024 * 1024;
        if (is_file($tmp) && filesize($tmp) + strlen($bin) > $max) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'Arquivo passou do limite de ' . PluginIdentificarcomputadorConfig::getMaxUploadMb() . ' MB.'];
        }
        $ok = file_put_contents($tmp, $bin, FILE_APPEND | LOCK_EX) !== false;
        return ['success' => $ok, 'recebido' => is_file($tmp) ? filesize($tmp) : 0];
    }

    /** Finaliza o upload: valida formato/tamanho, move para a pasta final e cria o registro. */
    public static function finalizarUpload(string $uploadId, int $sessoesId, string $nome, int $usersId): array
    {
        global $DB;
        if (!self::sessaoOnline($sessoesId)) {
            return ['success' => false, 'message' => 'A máquina não está mais conectada.'];
        }
        $tmp = self::dirTemp() . '/' . (preg_match('/^[a-f0-9]{8,64}$/', $uploadId) ? $uploadId : 'x') . '.part';
        if (!is_file($tmp)) {
            return ['success' => false, 'message' => 'Upload não encontrado.'];
        }
        $nome = self::nomeSeguro($nome);
        $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($ext, PluginIdentificarcomputadorConfig::formatosAceitos(), true)) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'Formato não permitido: .' . $ext];
        }
        $tam = filesize($tmp);
        $destino = PluginIdentificarcomputadorConfig::dirScripts() . '/' . $sessoesId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@rename($tmp, $destino)) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
        }
        $DB->insert(self::T_ARQUIVOS, [
            'sessoes_id' => $sessoesId,
            'users_id'   => $usersId,
            'nome'       => $nome,
            'formato'    => $ext,
            'tamanho'    => $tam,
            'caminho'    => $destino,
        ]);
        return ['success' => true, 'id' => (int) $DB->insertId(), 'nome' => $nome, 'formato' => $ext, 'tamanho' => (int) $tam];
    }

    private static function nomeSeguro(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        $nome = preg_replace('/[^A-Za-z0-9._ \-]/', '_', $nome);
        return mb_substr(trim($nome), 0, 200) ?: 'script';
    }

    /** Enfileira a execucao de um arquivo. Registra quem mandou. */
    public static function executar(int $arquivosId, int $usersId): array
    {
        global $DB;
        $a = $DB->request(['FROM' => self::T_ARQUIVOS, 'WHERE' => ['id' => $arquivosId], 'LIMIT' => 1])->current();
        if (!$a) {
            return ['success' => false, 'message' => 'Arquivo não encontrado.'];
        }
        $sessoesId = (int) $a['sessoes_id'];
        $s = self::sessaoOnline($sessoesId);
        if (!$s) {
            return ['success' => false, 'message' => 'A máquina não está mais conectada.'];
        }
        $DB->insert(self::T_JOBS, [
            'sessoes_id'      => $sessoesId,
            'arquivos_id'     => $arquivosId,
            'users_id'        => $usersId,
            'computadores_id' => $s['computadores_id'],
            'hostname'        => $s['hostname'],
            'nome'            => (string) $a['nome'],
            'formato'         => (string) $a['formato'],
            'tamanho'         => (int) $a['tamanho'],
            'status'          => 'pendente',
        ]);
        return ['success' => true, 'id' => (int) $DB->insertId()];
    }

    public static function statusJob(int $jobId): array
    {
        global $DB;
        $j = $DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['id' => $jobId], 'LIMIT' => 1])->current();
        if (!$j) {
            return ['success' => false, 'message' => 'Execução não encontrada.'];
        }
        return [
            'success'  => true,
            'status'   => (string) $j['status'],
            'exit'     => $j['exit_code'] === null ? null : (int) $j['exit_code'],
            'saida'    => (string) $j['saida'],
            'duracao'  => (int) $j['duracao_seg'],
        ];
    }

    public static function cancelar(int $jobId): array
    {
        global $DB;
        $j = $DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['id' => $jobId], 'LIMIT' => 1])->current();
        if (!$j) {
            return ['success' => false, 'message' => 'Execução não encontrada.'];
        }
        if ($j['status'] !== 'pendente') {
            return ['success' => false, 'message' => 'Só dá para cancelar enquanto está pendente.'];
        }
        $DB->update(self::T_JOBS, ['status' => 'cancelado', 'date_fim' => date('Y-m-d H:i:s')], ['id' => $jobId]);
        return ['success' => true];
    }

    /** Logs de execucao (todas as execucoes). */
    public static function logs(array $filtros = []): array
    {
        global $DB;
        $where = [];
        $busca = trim((string) ($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $where[] = ['OR' => [
                self::T_JOBS . '.nome'     => ['LIKE', $like],
                self::T_JOBS . '.hostname' => ['LIKE', $like],
                self::T_JOBS . '.formato'  => ['LIKE', $like],
                self::T_JOBS . '.status'   => ['LIKE', $like],
            ]];
        }
        $out = [];
        $req = $DB->request([
            'SELECT'    => [
                self::T_JOBS . '.*',
                'glpi_users.name AS usuario_name',
                'glpi_users.realname AS usuario_real',
                'glpi_users.firstname AS usuario_first',
            ],
            'FROM'      => self::T_JOBS,
            'LEFT JOIN' => ['glpi_users' => ['ON' => [self::T_JOBS => 'users_id', 'glpi_users' => 'id']]],
            'WHERE'     => $where,
            'ORDER'     => self::T_JOBS . '.id DESC',
            'LIMIT'     => 2000,
        ]);
        foreach ($req as $j) {
            $nome = trim((string) ($j['usuario_first'] . ' ' . $j['usuario_real']));
            if ($nome === '') { $nome = (string) $j['usuario_name']; }
            $out[] = [
                'id'        => (int) $j['id'],
                'usuario'   => $nome !== '' ? $nome : '#' . (int) $j['users_id'],
                'arquivo'   => (string) $j['nome'],
                'formato'   => (string) $j['formato'],
                'tamanho'   => (int) $j['tamanho'],
                'status'    => (string) $j['status'],
                'exit'      => $j['exit_code'] === null ? '' : (int) $j['exit_code'],
                'inicio'    => $j['date_inicio'] ? Html::convDateTime($j['date_inicio']) : '',
                'fim'       => $j['date_fim'] ? Html::convDateTime($j['date_fim']) : '',
                'envio'     => Html::convDateTime($j['date_creation']),
                'duracao'   => self::duracao((int) $j['duracao_seg']),
                'hostname'  => (string) $j['hostname'],
                'computadores_id' => (int) $j['computadores_id'],
            ];
        }
        return $out;
    }

    public static function saidaJob(int $jobId): string
    {
        global $DB;
        $j = $DB->request(['SELECT' => ['saida'], 'FROM' => self::T_JOBS, 'WHERE' => ['id' => $jobId], 'LIMIT' => 1])->current();
        return $j ? (string) $j['saida'] : '';
    }

    private static function duracao(int $seg): string
    {
        if ($seg <= 0) { return '-'; }
        if ($seg < 60) { return $seg . 's'; }
        $m = intdiv($seg, 60);
        $s = $seg % 60;
        return $m . 'min ' . $s . 's';
    }

    // ================================================================ MANUTENCAO

    /** Desativa sessoes vencidas, marca jobs travados como erro e apaga arquivos antigos. */
    public static function manutencao(): int
    {
        global $DB;
        $n = 0;
        $limite  = date('Y-m-d H:i:s', time() - self::ONLINE_SEG * 3);
        $agora   = date('Y-m-d H:i:s');

        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::T_SESSOES, 'WHERE' => ['ativo' => 1, 'OR' => [['last_seen' => ['<', $limite]], ['date_expiracao' => ['<', $agora]]]]]) as $s) {
            $DB->update(self::T_SESSOES, ['ativo' => 0], ['id' => (int) $s['id']]);
            $n++;
        }
        // Jobs presos (executando ha mais de 15 min) viram erro
        $travado = date('Y-m-d H:i:s', time() - 900);
        $DB->update(self::T_JOBS, ['status' => 'erro', 'saida' => 'Sem resposta da máquina (sessão encerrada).', 'date_fim' => $agora], ['status' => 'executando', 'date_inicio' => ['<', $travado]]);
        // Jobs pendentes de sessoes ja inativas viram cancelado
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::T_JOBS, 'WHERE' => ['status' => 'pendente']]) as $j) {
            $jj = $DB->request(['FROM' => self::T_JOBS, 'WHERE' => ['id' => (int) $j['id']], 'LIMIT' => 1])->current();
            $s = $DB->request(['SELECT' => ['ativo'], 'FROM' => self::T_SESSOES, 'WHERE' => ['id' => (int) $jj['sessoes_id']], 'LIMIT' => 1])->current();
            if (!$s || (int) $s['ativo'] === 0) {
                $DB->update(self::T_JOBS, ['status' => 'cancelado', 'date_fim' => $agora], ['id' => (int) $j['id']]);
            }
        }
        // Arquivos com mais de 2 dias sao apagados do disco (o log de execucao permanece)
        $velho = date('Y-m-d H:i:s', time() - 2 * 86400);
        foreach ($DB->request(['FROM' => self::T_ARQUIVOS, 'WHERE' => ['date_creation' => ['<', $velho]]]) as $a) {
            if (is_file($a['caminho'])) { @unlink($a['caminho']); }
            $DB->delete(self::T_ARQUIVOS, ['id' => (int) $a['id']]);
            $n++;
        }
        return $n;
    }
}
