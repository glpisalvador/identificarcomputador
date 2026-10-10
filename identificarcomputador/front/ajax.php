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

$action = (string) ($_POST['action'] ?? $_REQUEST['action'] ?? '');
$Comp   = PluginIdentificarcomputadorComputador::class;
$Ponte  = PluginIdentificarcomputadorPonte::class;

// ---------------------------------------------------------------- ponte de execucao remota
if (strpos($action, 'ponte_') === 0) {
    if (!PluginIdentificarcomputadorConfig::podeExecutar()) {
        $responder(['success' => false, 'message' => 'Sem permissão para executar scripts.']);
    }
    $uid = (int) Session::getLoginUserID();
    switch ($action) {
        case 'ponte_online':
            $responder(['success' => true, 'sessoes' => $Ponte::sessoesOnline()]);
            break;

        case 'ponte_arquivos':
            $responder(['success' => true, 'arquivos' => $Ponte::arquivosDaSessao((int) ($_POST['sessao'] ?? 0))]);
            break;

        case 'ponte_chunk':
            $r = $Ponte::gravarChunk((string) ($_POST['upload_id'] ?? ''), (int) ($_POST['indice'] ?? 0), base64_decode((string) ($_POST['dados'] ?? ''), true) ?: '');
            $responder($r);
            break;

        case 'ponte_finalizar':
            Session::checkCSRF($_POST);
            $responder($Ponte::finalizarUpload((string) ($_POST['upload_id'] ?? ''), (int) ($_POST['sessao'] ?? 0), (string) ($_POST['nome'] ?? ''), $uid));
            break;

        case 'ponte_executar':
            Session::checkCSRF($_POST);
            $responder($Ponte::executar((int) ($_POST['arquivo'] ?? 0), $uid));
            break;

        case 'ponte_job':
            $responder($Ponte::statusJob((int) ($_POST['id'] ?? 0)));
            break;

        case 'ponte_cancelar':
            Session::checkCSRF($_POST);
            $responder($Ponte::cancelar((int) ($_POST['id'] ?? 0)));
            break;

        case 'ponte_logs':
            $responder(['success' => true, 'linhas' => $Ponte::logs(['busca' => (string) ($_POST['busca'] ?? '')])]);
            break;

        case 'ponte_saida':
            $responder(['success' => true, 'saida' => $Ponte::saidaJob((int) ($_POST['id'] ?? 0))]);
            break;

        default:
            $responder(['success' => false, 'message' => 'Ação desconhecida.']);
    }
}

// ---------------------------------------------------------------- painel (ver resultados)
if (!PluginIdentificarcomputadorConfig::podeVer()) {
    $responder(['success' => false, 'message' => 'Sem permissão para ver os resultados.']);
}

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

    case 'enviar_itil':
        Session::checkCSRF($_POST);
        $id    = (int) ($_POST['id'] ?? 0);
        $tipo  = (string) ($_POST['tipo'] ?? '');
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($id <= 0 || $itemId <= 0) {
            $responder(['success' => false, 'message' => 'Informe o número do item.']);
        }
        $responder($Comp::enviarParaItil($id, $tipo, $itemId));
        break;

    case 'converter_ativo':
        Session::checkCSRF($_POST);
        $id  = (int) ($_POST['id'] ?? 0);
        $ent = (int) ($_POST['entidade'] ?? -1);
        if ($id <= 0 || $ent < 0) {
            $responder(['success' => false, 'message' => 'Selecione a entidade.']);
        }
        $responder($Comp::converterParaAtivo($id, $ent));
        break;

    default:
        $responder(['success' => false, 'message' => 'Ação desconhecida.']);
}
