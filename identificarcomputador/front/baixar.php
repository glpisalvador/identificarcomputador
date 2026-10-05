<?php

/**
 * Gera e entrega o script .cmd para o usuario autorizado.
 * Cada download cria um codigo valido pelos minutos configurados (padrao 30).
 */

Session::checkLoginUser();

if (!PluginIdentificarcomputadorConfig::podeBaixar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$token = PluginIdentificarcomputadorScript::gerarToken((int) Session::getLoginUserID());
$cmd   = PluginIdentificarcomputadorScript::gerarCmd($token);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="Identificar-Meu-Computador.cmd"');
header('Content-Length: ' . strlen($cmd));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

echo $cmd;
exit;
