<?php

/**
 * Pagina principal com duas abas:
 *   1) Computadores identificados (painel: cartoes, graficos, filtros e lista)
 *   2) Execucoes remotas (maquinas online, envio/execucao de scripts e logs)
 * O conteudo dinamico e carregado pelos JS via front/ajax.php.
 */

Session::checkLoginUser();

$C = PluginIdentificarcomputadorConfig::class;
$e = [$C, 'e'];

if (!$C::podeVer()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

Html::header('Identificar Computador', $_SERVER['PHP_SELF'] ?? '', 'tools', 'PluginIdentificarcomputadorMenu', '');

$podeExecutar = $C::podeExecutar();
$ponteMin     = PluginIdentificarcomputadorScript::PONTE_MINUTOS;

$cfg = [
    'url_ajax'    => $C::url('ajax.php'),
    'url_baixar'  => $C::url('baixar.php'),
    'url_detalhe' => $C::url('computador.form.php'),
    'csrf'        => Session::getNewCSRFToken(),
    'podeExcluir' => Session::haveRight('config', UPDATE),
    'podeBaixar'  => $C::podeBaixar(),
    'podeExecutar'=> $podeExecutar,
    'minutos'     => $C::getTokenMinutos(),
];
$cfgExec = [
    'url_ajax'     => $C::url('ajax.php'),
    'url_detalhe'  => $C::url('computador.form.php'),
    'url_baixar'   => $C::url('baixar.php', ['tipo' => 'ponte']),
    'csrf'         => Session::getNewCSRFToken(),
    'maxUploadMb'  => $C::getMaxUploadMb(),
    'formatos'     => $C::formatosAceitos(),
    'ponteMinutos' => $ponteMin,
];
$aceitos = '.' . implode(',.', $C::formatosAceitos());
?>
<div class="identificarcomputador-painel">

  <div class="identificarcomputador-abas identificarcomputador-topabas" role="tablist">
    <button type="button" class="identificarcomputador-aba ativa" data-topaba="computadores"><i class="ti ti-device-desktop-search"></i> Computadores identificados</button>
    <?php if ($podeExecutar) { ?>
      <button type="button" class="identificarcomputador-aba" data-topaba="execucoes"><i class="ti ti-terminal-2"></i> Execuções remotas</button>
    <?php } ?>
  </div>

  <!-- ===================== ABA 1: COMPUTADORES ===================== -->
  <div data-toppanel="computadores">
    <div class="identificarcomputador-topo">
      <div class="identificarcomputador-acoes-topo" style="margin-left:auto">
        <?php if ($cfg['podeBaixar']) { ?>
          <a href="<?php echo $e($cfg['url_baixar']); ?>" class="btn identificarcomputador-btn-principal" id="identificarcomputador-baixar">
            <i class="ti ti-download"></i> Identificar computador
          </a>
        <?php } ?>
        <button type="button" class="btn identificarcomputador-btn" data-ic-exportar="excel"><i class="ti ti-file-spreadsheet"></i> Excel</button>
        <button type="button" class="btn identificarcomputador-btn" data-ic-exportar="csv"><i class="ti ti-file"></i> CSV</button>
      </div>
    </div>

    <?php if ($cfg['podeBaixar']) { ?>
    <div class="identificarcomputador-dica-baixar">
      <i class="ti ti-info-circle"></i>
      <span>O botão baixa um arquivo <b>.cmd</b> que só identifica a máquina. Na máquina Windows, execute-o como administrador: ele coleta as informações, envia a este GLPI e fecha. O arquivo vale por <b><?php echo (int) $cfg['minutos']; ?> minutos</b> após o download.</span>
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

  <?php if ($podeExecutar) { ?>
  <!-- ===================== ABA 2: EXECUCOES REMOTAS ===================== -->
  <div data-toppanel="execucoes" style="display:none">
    <div class="identificarcomputador-topo">
      <div class="identificarcomputador-acoes-topo" style="margin-left:auto">
        <a href="<?php echo $e($cfgExec['url_baixar']); ?>" class="btn identificarcomputador-btn-principal" id="identificarcomputador-baixar-ponte">
          <i class="ti ti-download"></i> Habilitar execução remota
        </a>
      </div>
    </div>

    <div class="identificarcomputador-dica-baixar">
      <i class="ti ti-info-circle"></i>
      <span>Baixe o arquivo <b>.cmd</b> acima e execute-o como administrador na máquina. Ela abre um canal por <b><?php echo (int) $ponteMin; ?> minutos</b> (ou até a janela ser fechada) e aparece em <b>Máquinas online</b>. Então envie os arquivos (<?php echo $e(implode(', ', $C::formatosAceitos())); ?> — até <?php echo (int) $cfgExec['maxUploadMb']; ?> MB cada) e escolha qual executar. Tudo roda como administrador e fica no log, com o retorno de sucesso/erro e a saída.</span>
    </div>

    <div class="identificarcomputador-exec-grade">
      <div class="identificarcomputador-exec-lista">
        <div class="identificarcomputador-exec-sub"><i class="ti ti-plug-connected"></i> Máquinas online <span id="ic-exec-cont" class="identificarcomputador-pill off">0</span></div>
        <div id="ic-exec-sessoes"></div>
      </div>
      <div class="identificarcomputador-exec-painel" id="ic-exec-painel">
        <div class="identificarcomputador-vazio-lista"><i class="ti ti-hand-finger"></i> Selecione uma máquina à esquerda.</div>
      </div>
    </div>

    <div class="identificarcomputador-bloco-tit" style="margin-top:14px"><i class="ti ti-history"></i> Logs de execução</div>
    <div class="identificarcomputador-tabela-cx">
      <table class="identificarcomputador-tabela" id="ic-logs-tabela">
        <thead>
          <tr>
            <th data-sort="usuario">Usuário (GLPI)</th>
            <th data-sort="arquivo">Arquivo</th>
            <th data-sort="formato">Formato</th>
            <th data-sort="tamanho">Tamanho</th>
            <th data-sort="status">Status</th>
            <th data-sort="exit">Código</th>
            <th data-sort="envio">Enviado</th>
            <th data-sort="inicio">Início</th>
            <th data-sort="duracao">Duração</th>
            <th data-sort="hostname">Computador</th>
            <th class="identificarcomputador-no-export">Saída</th>
          </tr>
        </thead>
        <tbody id="ic-logs-corpo"></tbody>
      </table>
      <div id="ic-logs-vazio" class="identificarcomputador-vazio-lista" style="display:none"><i class="ti ti-mood-empty"></i> Nenhuma execução registrada.</div>
    </div>
  </div>
  <input type="file" id="ic-exec-file" multiple accept="<?php echo $e($aceitos); ?>" style="display:none">
  <?php } ?>

</div>

<script>
window.identificarcomputadorCfg = <?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
<?php if ($podeExecutar) { ?>window.identificarcomputadorExec = <?php echo json_encode($cfgExec, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;<?php } ?>
</script>
<?php
Html::footer();
