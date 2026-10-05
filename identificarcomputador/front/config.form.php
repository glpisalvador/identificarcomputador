<?php

/**
 * Configuracao do plugin Identificar Computador (faz POST para si mesma).
 * Processa o POST em silencio e deixa a pagina renderizar normalmente (sem Html::back/redirect).
 */

Session::checkLoginUser();

$C = PluginIdentificarcomputadorConfig::class;
$e = [$C, 'e'];

if (!Session::haveRight('config', UPDATE)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$ids = static fn(string $campo): array => array_values(array_unique(array_filter(array_map('intval', (array) ($_POST[$campo] ?? [])), static fn($v) => $v > 0)));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_action'])) {
    switch ((string) $_POST['save_action']) {
        case 'salvar_acesso':
            $C::setArrayConfig('allowed_profiles_ver', $ids('allowed_profiles_ver'));
            $C::setArrayConfig('allowed_profiles_baixar', $ids('allowed_profiles_baixar'));
            Session::addMessageAfterRedirect('Permissões salvas.', false, INFO);
            break;

        case 'salvar_script':
            $min = max(1, min(1440, (int) ($_POST['token_minutos'] ?? 30)));
            $C::setConfig('token_minutos', (string) $min);
            $url = trim((string) ($_POST['url_recebimento'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                Session::addMessageAfterRedirect('A URL de recebimento deve começar com http:// ou https://.', false, ERROR);
                break;
            }
            $C::setConfig('url_recebimento', rtrim($url, '/'));
            Session::addMessageAfterRedirect('Configurações do script salvas.', false, INFO);
            break;
    }
}

Html::header('Identificar Computador', $_SERVER['PHP_SELF'] ?? '', 'config', 'PluginIdentificarcomputadorConfig', '');

$perfis       = $C::getTodosPerfis();
$selVer       = array_map('intval', $C::getArrayConfig('allowed_profiles_ver'));
$selBaixar    = array_map('intval', $C::getArrayConfig('allowed_profiles_baixar'));
$tokenMinutos = $C::getTokenMinutos();
$urlConfig    = (string) $C::getConfig('url_recebimento', '');
$urlEfetiva   = $C::getUrlRecebimento();
$token        = Session::getNewCSRFToken();

// Lista de perfis como checkboxes com busca (selecionados primeiro, filtro no JS)
$listaPerfis = static function (string $campo, array $selecionados) use ($perfis, $e): string {
    $sel = [];
    $nao = [];
    foreach ($perfis as $id => $nome) {
        $item = '<label class="identificarcomputador-perfil" data-busca="' . $e(mb_strtolower($nome)) . '">'
              . '<input type="checkbox" name="' . $campo . '[]" value="' . (int) $id . '"' . (in_array((int) $id, $selecionados, true) ? ' checked' : '') . '>'
              . '<span>' . $e($nome) . '</span></label>';
        if (in_array((int) $id, $selecionados, true)) { $sel[] = $item; } else { $nao[] = $item; }
    }
    $itens = implode('', $sel) . implode('', $nao);
    return '<div class="identificarcomputador-multi">'
         . '<input type="text" class="form-control form-control-sm identificarcomputador-busca-perfil" placeholder="Buscar perfil...">'
         . '<div class="identificarcomputador-perfis">' . ($itens !== '' ? $itens : '<div class="identificarcomputador-vazio">Nenhum perfil.</div>') . '</div>'
         . '</div>';
};
?>
<div class="identificarcomputador-config">

  <form method="post" action="<?php echo $e($C::url('config.form.php')); ?>" class="identificarcomputador-card">
    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $token; ?>">
    <input type="hidden" name="save_action" value="salvar_acesso">
    <div class="identificarcomputador-card-cab"><i class="ti ti-shield-lock"></i> Permissões por perfil</div>
    <p class="identificarcomputador-ajuda"><i class="ti ti-info-circle"></i> Quem pode ver os resultados e quem pode baixar o script. Perfis com direito de configuração do GLPI sempre têm acesso.</p>
    <div class="identificarcomputador-duas">
      <div>
        <label class="identificarcomputador-rot"><i class="ti ti-eye"></i> Ver os resultados</label>
        <?php echo $listaPerfis('allowed_profiles_ver', $selVer); ?>
      </div>
      <div>
        <label class="identificarcomputador-rot"><i class="ti ti-download"></i> Baixar o script</label>
        <?php echo $listaPerfis('allowed_profiles_baixar', $selBaixar); ?>
      </div>
    </div>
    <div class="identificarcomputador-rodape">
      <button type="submit" class="btn identificarcomputador-btn-salvar"><i class="ti ti-device-floppy"></i> Salvar permissões</button>
    </div>
  </form>

  <form method="post" action="<?php echo $e($C::url('config.form.php')); ?>" class="identificarcomputador-card">
    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $token; ?>">
    <input type="hidden" name="save_action" value="salvar_script">
    <div class="identificarcomputador-card-cab"><i class="ti ti-settings"></i> Script e envio</div>
    <div class="identificarcomputador-campo">
      <label class="identificarcomputador-rot">Validade do script baixado (minutos)</label>
      <input type="number" name="token_minutos" min="1" max="1440" value="<?php echo (int) $tokenMinutos; ?>" class="form-control form-control-sm" style="max-width:120px">
      <p class="identificarcomputador-ajuda"><i class="ti ti-clock"></i> Depois desse tempo o código do download deixa de ser aceito. Padrão: 30 minutos.</p>
    </div>
    <div class="identificarcomputador-campo">
      <label class="identificarcomputador-rot">Endereço do GLPI que as máquinas enxergam</label>
      <input type="text" name="url_recebimento" value="<?php echo $e($urlConfig); ?>" placeholder="<?php echo $e($urlEfetiva); ?>" class="form-control form-control-sm">
      <p class="identificarcomputador-ajuda"><i class="ti ti-world"></i> Deixe em branco para usar o endereço do GLPI. Preencha só se as máquinas acessam o GLPI por outro endereço. Em uso agora: <b><?php echo $e($urlEfetiva); ?></b></p>
    </div>
    <div class="identificarcomputador-rodape">
      <button type="submit" class="btn identificarcomputador-btn-salvar"><i class="ti ti-device-floppy"></i> Salvar configurações</button>
    </div>
  </form>

</div>
<?php
Html::footer();
