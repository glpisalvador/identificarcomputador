<?php

/**
 * Instalacao e desinstalacao do plugin Identificar Computador.
 *
 * Tabelas (prefixo glpi_plugin_identificarcomputador_):
 *   configs       - pares chave/valor (perfis permitidos, minutos do token, url de recebimento)
 *   computadores  - um registro por maquina (campos de resumo + dados_json completo)
 *   coletas       - historico de cada execucao do script na maquina
 *   tokens        - codigos de download, validos por 30 min, ligados a quem baixou
 *
 * REGRA DO PROJETO: a desinstalacao NUNCA remove as tabelas.
 */

function plugin_identificarcomputador_install(): bool
{
    global $DB;

    $p = 'glpi_plugin_identificarcomputador_';

    if (!$DB->tableExists($p . 'configs')) {
        $DB->doQuery("
            CREATE TABLE `{$p}configs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `value` text,
                `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$DB->tableExists($p . 'computadores')) {
        $DB->doQuery("
            CREATE TABLE `{$p}computadores` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `uuid` varchar(255) NOT NULL DEFAULT '',
                `serial_bios` varchar(255) NOT NULL DEFAULT '',
                `hostname` varchar(255) NOT NULL DEFAULT '',
                `dominio` varchar(255) NOT NULL DEFAULT '',
                `usuario_logado` varchar(255) NOT NULL DEFAULT '',
                `so` varchar(255) NOT NULL DEFAULT '',
                `so_versao` varchar(100) NOT NULL DEFAULT '',
                `so_build` varchar(100) NOT NULL DEFAULT '',
                `fabricante` varchar(255) NOT NULL DEFAULT '',
                `modelo` varchar(255) NOT NULL DEFAULT '',
                `serial_maquina` varchar(255) NOT NULL DEFAULT '',
                `placa_mae` varchar(255) NOT NULL DEFAULT '',
                `processador` varchar(255) NOT NULL DEFAULT '',
                `ram_total` bigint NOT NULL DEFAULT 0,
                `armazenamento_total` bigint NOT NULL DEFAULT 0,
                `placa_video` text,
                `antivirus` varchar(255) NOT NULL DEFAULT '',
                `antivirus_estado` varchar(100) NOT NULL DEFAULT '',
                `firewall` varchar(100) NOT NULL DEFAULT '',
                `acesso_remoto` text,
                `ips` text,
                `macs` text,
                `dados_json` longtext,
                `users_id_baixou` int unsigned NOT NULL DEFAULT 0,
                `qtd_coletas` int unsigned NOT NULL DEFAULT 1,
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `uuid` (`uuid`),
                KEY `serial_bios` (`serial_bios`),
                KEY `hostname` (`hostname`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$DB->tableExists($p . 'coletas')) {
        $DB->doQuery("
            CREATE TABLE `{$p}coletas` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `computadores_id` int unsigned NOT NULL DEFAULT 0,
                `dados_json` longtext,
                `ip_origem` varchar(100) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `computadores_id` (`computadores_id`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$DB->tableExists($p . 'tokens')) {
        $DB->doQuery("
            CREATE TABLE `{$p}tokens` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `token` varchar(64) NOT NULL,
                `users_id` int unsigned NOT NULL DEFAULT 0,
                `usado_em` timestamp NULL DEFAULT NULL,
                `ip_origem` varchar(100) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `date_expiracao` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Ponte de execucao remota: sessoes ativas, biblioteca de arquivos e fila/log de execucoes
    if (!$DB->tableExists($p . 'ponte_sessoes')) {
        $DB->doQuery("
            CREATE TABLE `{$p}ponte_sessoes` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `token` varchar(64) NOT NULL,
                `users_id_dono` int unsigned NOT NULL DEFAULT 0,
                `computadores_id` int unsigned NOT NULL DEFAULT 0,
                `hostname` varchar(255) NOT NULL DEFAULT '',
                `ip` varchar(100) NOT NULL DEFAULT '',
                `so` varchar(255) NOT NULL DEFAULT '',
                `usuario_logado` varchar(255) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `last_seen` timestamp NULL DEFAULT NULL,
                `date_expiracao` timestamp NULL DEFAULT NULL,
                `ativo` tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `ativo` (`ativo`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    if (!$DB->tableExists($p . 'ponte_arquivos')) {
        $DB->doQuery("
            CREATE TABLE `{$p}ponte_arquivos` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `sessoes_id` int unsigned NOT NULL DEFAULT 0,
                `users_id` int unsigned NOT NULL DEFAULT 0,
                `nome` varchar(255) NOT NULL DEFAULT '',
                `formato` varchar(20) NOT NULL DEFAULT '',
                `tamanho` bigint NOT NULL DEFAULT 0,
                `caminho` varchar(255) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `sessoes_id` (`sessoes_id`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    if (!$DB->tableExists($p . 'ponte_jobs')) {
        $DB->doQuery("
            CREATE TABLE `{$p}ponte_jobs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `sessoes_id` int unsigned NOT NULL DEFAULT 0,
                `arquivos_id` int unsigned NOT NULL DEFAULT 0,
                `users_id` int unsigned NOT NULL DEFAULT 0,
                `computadores_id` int unsigned NOT NULL DEFAULT 0,
                `hostname` varchar(255) NOT NULL DEFAULT '',
                `nome` varchar(255) NOT NULL DEFAULT '',
                `formato` varchar(20) NOT NULL DEFAULT '',
                `tamanho` bigint NOT NULL DEFAULT 0,
                `status` varchar(20) NOT NULL DEFAULT 'pendente',
                `exit_code` int DEFAULT NULL,
                `saida` longtext,
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `date_inicio` timestamp NULL DEFAULT NULL,
                `date_fim` timestamp NULL DEFAULT NULL,
                `duracao_seg` int NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `sessoes_id` (`sessoes_id`),
                KEY `computadores_id` (`computadores_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Pasta dos arquivos enviados (fora da web; servidos so pelo endpoint com token)
    $dir = GLPI_DOC_DIR . '/_plugins/identificarcomputador/scripts';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Configuracoes padrao (so insere o que ainda nao existe)
    $padroes = [
        'allowed_profiles_ver'      => '[]',
        'allowed_profiles_baixar'   => '[]',
        'allowed_profiles_executar' => '[]',
        'token_minutos'             => '30',
        'url_recebimento'           => '',
        'max_upload_mb'             => '100',
    ];
    foreach ($padroes as $nome => $valor) {
        $existe = $DB->request([
            'COUNT' => 'total',
            'FROM'  => $p . 'configs',
            'WHERE' => ['name' => $nome],
        ])->current();
        if ((int) ($existe['total'] ?? 0) === 0) {
            $DB->insert($p . 'configs', ['name' => $nome, 'value' => $valor]);
        }
    }

    // Acao automatica: remove os codigos de download vencidos
    CronTask::register('PluginIdentificarcomputadorScript', 'limparTokens', HOUR_TIMESTAMP, [
        'state' => CronTask::STATE_WAITING,
        'mode'  => CronTask::MODE_INTERNAL,
        'comment' => 'Remove os codigos de download expirados do Identificar Computador',
    ]);

    return true;
}

function plugin_identificarcomputador_uninstall(): bool
{
    // Tabelas mantidas de proposito. Nada e dropado aqui.
    return true;
}
