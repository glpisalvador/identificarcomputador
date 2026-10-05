<?php

/**
 * Endpoint AJAX do painel: lista filtrada, opcoes dos filtros, dados dos graficos e exclusao.
 * Sempre responde JSON e inclui new_token para renovar o CSRF no frontend.
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(static function (): void {
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Erro interno.']);
    }
});

Session::checkLoginUser();

$responder = static function (array $r): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $r['new_token'] = Session::getNewCSRFToken();
    echo json_encode($r);
    exit;
};

if (!PluginIdentificarcomputadorConfig::podeVer()) {
    $responder(['success' => false, 'message' => 'Sem permissão para ver os resultados.']);
}

$Comp   = PluginIdentificarcomputadorComputador::class;
$action = (string) ($_POST['action'] ?? $_REQUEST['action'] ?? '');

switch ($action) {
    case 'listar':
        $filtros = [
            'busca'             => (string) ($_POST['busca'] ?? ''),
            'so'                => (array) ($_POST['so'] ?? []),
            'fabricante'        => (array) ($_POST['fabricante'] ?? []),
            'antivirus'         => (array) ($_POST['antivirus'] ?? []),
            'firewall'          => (array) ($_POST['firewall'] ?? []),
            'dominio'           => (array) ($_POST['dominio'] ?? []),
            'com_acesso_remoto' => !empty($_POST['com_acesso_remoto']),
            'data_de'           => (string) ($_POST['data_de'] ?? ''),
            'data_ate'          => (string) ($_POST['data_ate'] ?? ''),
        ];
        $responder([
            'success'  => true,
            'linhas'   => $Comp::listar($filtros),
            'resumo'   => $Comp::resumoCards(),
            'graficos' => $Comp::dadosGraficos(),
        ]);
        break;

    case 'opcoes':
        $responder(['success' => true, 'opcoes' => $Comp::opcoesFiltros()]);
        break;

    case 'excluir':
        if (!Session::haveRight('config', UPDATE)) {
            $responder(['success' => false, 'message' => 'Sem permissão para excluir.']);
        }
        Session::checkCSRF($_POST);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $responder(['success' => false, 'message' => 'Registro inválido.']);
        }
        $ok = $Comp::excluir($id);
        $responder(['success' => $ok, 'message' => $ok ? 'Computador removido.' : 'Falha ao remover.']);
        break;

    default:
        $responder(['success' => false, 'message' => 'Ação desconhecida.']);
}
