<?php

/**
 * Endpoint da ponte de execucao remota (sem login; autorizado pelo token de 30 min).
 *   action=poll    -> registra a sessao e devolve os jobs a executar (JSON)
 *   action=baixar  -> entrega o arquivo de um job (binario)
 *   action=result  -> recebe o resultado de um job (JSON)
 *
 * Carregado pelo GLPI 11/12 sem autenticacao (Firewall NO_CHECK + caminho stateless no setup.php).
 */

$acao  = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';

// ---- baixar: resposta binaria (antes de qualquer saida) ----
if ($acao === 'baixar') {
    $jobId = (int) ($_GET['job'] ?? 0);
    $arq = PluginIdentificarcomputadorPonte::arquivoDoJob($jobId, $token);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if ($arq === null) {
        http_response_code(403);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($arq['nome']) . '"');
    header('Content-Length: ' . filesize($arq['caminho']));
    header('X-Content-Type-Options: nosniff');
    readfile($arq['caminho']);
    exit;
}

// ---- demais acoes: JSON ----
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

register_shutdown_function(static function (): void {
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Erro interno.']);
    }
});

$responder = static function (array $r): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($r);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $responder(['success' => false, 'message' => 'Metodo invalido.']);
}

$corpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($corpo)) { $corpo = []; }

if ($acao === 'poll') {
    $sessoesId = PluginIdentificarcomputadorPonte::registrarSessao($token, $corpo, (string) $ip);
    if ($sessoesId === null) {
        $responder(['success' => false, 'message' => 'Codigo invalido ou expirado.', 'parar' => true]);
    }
    $jobs = PluginIdentificarcomputadorPonte::jobsParaAgente($sessoesId);
    $responder(['success' => true, 'jobs' => $jobs]);
}

if ($acao === 'result') {
    $jobId = (int) ($_GET['job'] ?? $corpo['job'] ?? 0);
    $exit  = array_key_exists('exit', $corpo) && $corpo['exit'] !== null ? (int) $corpo['exit'] : null;
    $saida = (string) ($corpo['saida'] ?? '');
    $ok = PluginIdentificarcomputadorPonte::registrarResultado($jobId, $token, $exit, $saida);
    $responder(['success' => $ok]);
}

$responder(['success' => false, 'message' => 'Acao desconhecida.']);
