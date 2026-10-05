<?php

/**
 * Recebe os dados enviados pela maquina Windows (sem login).
 * Autorizado apenas por um codigo valido (token de 30 min). Responde sempre em JSON.
 *
 * Carregado pelo GLPI 11/12 sem autenticacao (Firewall NO_CHECK + caminho stateless no setup.php).
 */

// Garante resposta JSON limpa, mesmo diante de warnings de outros plugins
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

register_shutdown_function(static function (): void {
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Erro interno ao processar o envio.']);
    }
});

$responder = static function (array $r): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($r);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $responder(['success' => false, 'message' => 'Metodo invalido.']);
}

$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

$users_id = PluginIdentificarcomputadorScript::validarToken($token, $ip);
if ($users_id === null) {
    $responder(['success' => false, 'message' => 'Codigo invalido ou expirado. Baixe o script novamente.']);
}

$corpo = file_get_contents('php://input');
$dados = json_decode((string) $corpo, true);
if (!is_array($dados) || $dados === []) {
    $responder(['success' => false, 'message' => 'Conteudo invalido.']);
}

// Limite de seguranca: ~8 MB de payload
if (strlen((string) $corpo) > 8 * 1024 * 1024) {
    $responder(['success' => false, 'message' => 'Envio grande demais.']);
}

$res = PluginIdentificarcomputadorColeta::processar($dados, (int) $users_id, (string) $ip);
$responder($res);
