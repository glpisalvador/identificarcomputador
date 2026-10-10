<?php

/**
 * Painel dos computadores identificados: cartoes, graficos, filtros e lista.
 * O conteudo dinamico e carregado pelo identificarcomputador.js via front/ajax.php.
 */

Session::checkLoginUser();

$C = PluginIdentificarcomputadorConfig::class;
$e = [$C, 'e'];

if (!$C::podeVer()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

Html::header('Identificar Computador', $_SERVER['PHP_SELF'] ?? '', 'tools', 'PluginIdentificarcomputadorMenu', '');

$cfg = [
    'url_ajax'    => $C::url('ajax.php'),
    'url_baixar'  => $C::url('baixar.php'),
    'url_detalhe' => $C::url('computador.form.php'),
    'csrf'        => Session::getNewCSRFToken(),
    'podeExcluir' => Session::haveRight('config', UPDATE),
    'podeBaixar'  => $C::podeBaixar(),
    'podeExecutar'=> $C::podeExecutar(),
    'url_execucao'=> $C::url('execucao.php'),
    'minutos'     => $C::getTokenMinutos(),
];
?>
<div class="identificarcomputador-painel">

  <div class="identificarcomputador-topo">
    <div class="identificarcomputador-titulo"><i class="ti ti-device-desktop-search"></i> Computadores identificados</div>
    <div class="identificarcomputador-acoes-topo">
      <?php if ($cfg['podeBaixar']) { ?>
        <a href="<?php echo $e($cfg['url_baixar']); ?>" class="btn identificarcomputador-btn-principal" id="identificarcomputador-baixar">
          <i class="ti ti-download"></i> Identifique meu computador
        </a>
      <?php } ?>
      <?php if ($cfg['podeExecutar']) { ?>
        <a href="<?php echo $e($cfg['url_execucao']); ?>" class="btn identificarcomputador-btn"><i class="ti ti-terminal-2"></i> Execução remota</a>
      <?php } ?>
      <button type="button" class="btn identificarcomputador-btn" data-ic-exportar="excel"><i class="ti ti-file-spreadsheet"></i> Excel</button>
      <button type="button" class="btn identificarcomputador-btn" data-ic-exportar="csv"><i class="ti ti-file"></i> CSV</button>
    </div>
  </div>

  <?php if ($cfg['podeBaixar']) { ?>
  <div class="identificarcomputador-dica-baixar">
    <i class="ti ti-info-circle"></i>
    <span>O botão baixa um arquivo <b>.cmd</b>. Na máquina Windows, execute-o como administrador. Ele coleta as informações e envia a este GLPI. O arquivo vale por <b><?php echo (int) $cfg['minutos']; ?> minutos</b> após o download.</span>
  </div>
  <?php } ?>

  <div class="identificarcomputador-cards" id="identificarcomputador-cards"></div>

  <div class="identificarcomputador-graficos">
    <div class="identificarcomputador-grafico-cx"><div class="identificarcomputador-grafico-tit">Sistema operacional</div><div id="identificarcomputador-g-so" class="identificarcomputador-grafico"></div></div>
    <div class="identificarcomputador-grafico-cx"><div class="identificarcomputador-grafico-tit">Fabricante</div><div id="identificarcomputador-g-fab" class="identificarcomputador-grafico"></div></div>
    <div class="identificarcomputador-grafico-cx"><div class="identificarcomputador-grafico-tit">Antivírus</div><div id="identificarcomputador-g-av" class="identificarcomputador-grafico"></div></div>
    <div class="identificarcomputador-grafico-cx"><div class="identificarcomputador-grafico-tit">Firewall</div><div id="identificarcomputador-g-fw" class="identificarcomputador-grafico"></div></div>
  </div>

  <div class="identificarcomputador-filtros" id="identificarcomputador-filtros">
    <div class="identificarcomputador-filtro">
      <label>Buscar</label>
      <input type="text" id="ic-f-busca" class="form-control form-control-sm" placeholder="Hostname, usuário, IP, MAC, modelo...">
    </div>
    <div class="identificarcomputador-filtro"><label>Sistema</label><select id="ic-f-so" multiple class="identificarcomputador-sel"></select></div>
    <div class="identificarcomputador-filtro"><label>Fabricante</label><select id="ic-f-fabricante" multiple class="identificarcomputador-sel"></select></div>
    <div class="identificarcomputador-filtro"><label>Antivírus</label><select id="ic-f-antivirus" multiple class="identificarcomputador-sel"></select></div>
    <div class="identificarcomputador-filtro"><label>Firewall</label><select id="ic-f-firewall" multiple class="identificarcomputador-sel"></select></div>
    <div class="identificarcomputador-filtro"><label>Domínio</label><select id="ic-f-dominio" multiple class="identificarcomputador-sel"></select></div>
    <div class="identificarcomputador-filtro"><label>De</label><input type="date" id="ic-f-de" class="form-control form-control-sm"></div>
    <div class="identificarcomputador-filtro"><label>Até</label><input type="date" id="ic-f-ate" class="form-control form-control-sm"></div>
    <div class="identificarcomputador-filtro identificarcomputador-filtro-check">
      <label class="identificarcomputador-check"><input type="checkbox" id="ic-f-remoto"> Com acesso remoto</label>
    </div>
    <div class="identificarcomputador-filtro">
      <button type="button" class="btn identificarcomputador-btn" id="ic-limpar"><i class="ti ti-filter-off"></i> Limpar</button>
    </div>
  </div>

  <div class="identificarcomputador-tabela-cx">
    <table class="identificarcomputador-tabela" id="identificarcomputador-tabela">
      <thead>
        <tr>
          <th data-sort="hostname">Computador</th>
          <th data-sort="usuario">Usuário</th>
          <th data-sort="so">Sistema</th>
          <th data-sort="fabricante">Fabricante / Modelo</th>
          <th data-sort="ram_gb">RAM</th>
          <th data-sort="disco_gb">Disco</th>
          <th data-sort="antivirus">Antivírus</th>
          <th data-sort="firewall">Firewall</th>
          <th data-sort="acesso_remoto">Acesso remoto</th>
          <th data-sort="ultima">Última coleta</th>
          <th class="identificarcomputador-no-export">Ações</th>
        </tr>
      </thead>
      <tbody id="identificarcomputador-corpo"></tbody>
    </table>
    <div id="identificarcomputador-vazio" class="identificarcomputador-vazio-lista" style="display:none">
      <i class="ti ti-mood-empty"></i> Nenhum computador encontrado.
    </div>
  </div>

  <div class="identificarcomputador-paginacao" id="identificarcomputador-paginacao"></div>

</div>

<script>window.identificarcomputadorCfg = <?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php
Html::footer();
