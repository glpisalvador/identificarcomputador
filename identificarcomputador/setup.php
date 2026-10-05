<?php

/**
 * Plugin Identificar Computador - GLPI 11 e 12
 *
 * Gera um script .cmd que o usuario executa como administrador numa maquina Windows.
 * O script coleta as informacoes do computador (hardware, rede, portas, seguranca,
 * programas) e envia para o GLPI, que mostra tudo num painel com filtros.
 *
 * Independente do inventario nativo do GLPI: usa apenas tabelas proprias.
 */

define('PLUGIN_IDENTIFICARCOMPUTADOR_VERSION', '1.0.2');
define('PLUGIN_IDENTIFICARCOMPUTADOR_MIN_GLPI', '11.0.0');
define('PLUGIN_IDENTIFICARCOMPUTADOR_MAX_GLPI', '12.99.99');

function plugin_init_identificarcomputador(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // Chave literal: a constante Hooks::CSRF_COMPLIANT nao existe no GLPI 12
    $PLUGIN_HOOKS['csrf_compliant']['identificarcomputador'] = true;

    // Pagina que recebe os dados do script: sem login e sem sessao (autorizada pelo token de 30 min)
    if (class_exists(\Glpi\Http\Firewall::class)) {
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('identificarcomputador', '#^/front/receber\.php$#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
    }
    if (class_exists(\Glpi\Http\SessionManager::class)) {
        \Glpi\Http\SessionManager::registerPluginStatelessPath('identificarcomputador', '#^/front/receber\.php$#');
    }

    $plugin = new Plugin();
    if (!$plugin->isActivated('identificarcomputador')) {
        return;
    }

    Plugin::registerClass('PluginIdentificarcomputadorConfig');
    Plugin::registerClass('PluginIdentificarcomputadorMenu');
    Plugin::registerClass('PluginIdentificarcomputadorComputador');

    // Pagina de configuracao acessivel pelo icone do plugin em Configurar > Plugins
    $PLUGIN_HOOKS['config_page']['identificarcomputador'] = 'front/config.php';
    // Item simples dentro do menu Ferramentas
    $PLUGIN_HOOKS['menu_toadd']['identificarcomputador'] = ['tools' => 'PluginIdentificarcomputadorMenu'];

    // Assets carregados apenas nas paginas do proprio plugin
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/plugins/identificarcomputador/') !== false) {
        $PLUGIN_HOOKS['add_css']['identificarcomputador']        = 'public/css/identificarcomputador.css';
        $PLUGIN_HOOKS['add_javascript']['identificarcomputador'] = 'public/js/identificarcomputador.js';
    }
}

function plugin_version_identificarcomputador(): array
{
    return [
        'name'         => 'Identificar Computador',
        'version'      => PLUGIN_IDENTIFICARCOMPUTADOR_VERSION,
        'author'       => 'GLPI Salvador',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_IDENTIFICARCOMPUTADOR_MIN_GLPI,
                'max' => PLUGIN_IDENTIFICARCOMPUTADOR_MAX_GLPI,
            ],
            'php'  => ['min' => '8.1'],
        ],
    ];
}

function plugin_identificarcomputador_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_IDENTIFICARCOMPUTADOR_MIN_GLPI, '>=');
}

function plugin_identificarcomputador_check_config($verbose = false): bool
{
    return true;
}
