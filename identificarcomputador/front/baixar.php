<?php

/**
 * Gera e entrega o script .cmd para o usuario autorizado.
 *   tipo=inventario (padrao) -> so identifica o computador e fecha (precisa de "baixar")
 *   tipo=ponte               -> abre a execucao remota por 10 min (precisa de "executar")
 * Cada download cria um codigo valido pelos minutos configurados (padrao 30).
 */

Session::checkLoginUser();

$tipo = (string) ($_GET['tipo'] ?? 'inventario');

if ($tipo === 'ponte') {
    if (!PluginIdentificarcomputadorConfig::podeExecutar()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
} elseif (!PluginIdentificarcomputadorConfig::podeBaixar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$token = PluginIdentificarcomputadorScript::gerarToken((int) Session::getLoginUserID());
if ($tipo === 'ponte') {
    $cmd   = PluginIdentificarcomputadorScript::gerarCmdPonte($token);
    $nome  = 'Execucao-Remota.cmd';
} else {
    $cmd   = PluginIdentificarcomputadorScript::gerarCmdInventario($token);
    $nome  = 'Identificar-Meu-Computador.cmd';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . strlen($cmd));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

echo $cmd;
exit;
