<?php

/**
 * Execucao remota: envia e executa scripts nas maquinas com o canal aberto,
 * e mostra o log de todas as execucoes.
 */

Session::checkLoginUser();

$C = PluginIdentificarcomputadorConfig::class;
$e = [$C, 'e'];

if (!$C::podeExecutar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

Html::header('Identificar Computador', $_SERVER['PHP_SELF'] ?? '', 'tools', 'PluginIdentificarcomputadorMenu', '');

$cfg = [
    'url_ajax'    => $C::url('ajax.php'),
    'url_detalhe' => $C::url('computador.form.php'),
    'csrf'        => Session::getNewCSRFToken(),
    'maxUploadMb' => $C::getMaxUploadMb(),
    'formatos'    => $C::formatosAceitos(),
];
$aceitos = '.' . implode(',.', $C::formatosAceitos());
?>
<div class="identificarcomputador-painel identificarcomputador-exec">

  <div class="identificarcomputador-topo">
    <div class="identificarcomputador-titulo"><i class="ti ti-terminal-2"></i> Execução remota</div>
    <div class="identificarcomputador-acoes-topo">
      <a class="btn identificarcomputador-btn" href="<?php echo $e($C::url('computador.php')); ?>"><i class="ti ti-device-desktop-search"></i> Computadores</a>
    </div>
  </div>

  <div class="identificarcomputador-abas" role="tablist">
    <button type="button" class="identificarcomputador-aba ativa" data-aba="ativas"><i class="ti ti-plug-connected"></i> Sessões ativas</button>
    <button type="button" class="identificarcomputador-aba" data-aba="logs"><i class="ti ti-history"></i> Logs</button>
  </div>

  <div class="identificarcomputador-aba-corpo ativa" data-corpo="ativas">
    <div class="identificarcomputador-dica-baixar">
      <i class="ti ti-info-circle"></i>
      <span>Aparecem aqui as máquinas com o script aberto (canal ativo por 30 minutos). Selecione uma, envie os arquivos
      (<?php echo $e(implode(', ', $C::formatosAceitos())); ?> — até <?php echo (int) $cfg['maxUploadMb']; ?> MB cada) e escolha qual executar. Tudo roda como administrador e fica no log.</span>
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
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="logs">
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

</div>

<input type="file" id="ic-exec-file" multiple accept="<?php echo $e($aceitos); ?>" style="display:none">
<script>window.identificarcomputadorExec = <?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php
Html::footer();
