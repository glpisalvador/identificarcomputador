<?php

/**
 * Detalhe de um computador identificado, em abas:
 * Resumo, Hardware, Rede e portas, Segurança, Programas e Histórico.
 */

Session::checkLoginUser();

$C = PluginIdentificarcomputadorConfig::class;
$Comp = PluginIdentificarcomputadorComputador::class;
$e = [$C, 'e'];

if (!$C::podeVer()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$id  = (int) ($_GET['id'] ?? 0);
$det = $id > 0 ? $Comp::getDetalhe($id) : null;

Html::header('Identificar Computador', $_SERVER['PHP_SELF'] ?? '', 'tools', 'PluginIdentificarcomputadorMenu', '');

if ($det === null) {
    echo '<div class="identificarcomputador-painel"><div class="identificarcomputador-vazio-lista"><i class="ti ti-mood-empty"></i> Computador não encontrado.</div>';
    echo '<p><a class="btn identificarcomputador-btn" href="' . $e($C::url('computador.php')) . '"><i class="ti ti-arrow-left"></i> Voltar</a></p></div>';
    Html::footer();
    return;
}

$d = $det['dados'];

/** Valor simples com fallback. */
$v = static function ($x, string $pad = '-') use ($e): string {
    if (is_array($x)) { $x = implode(', ', array_map('strval', $x)); }
    $x = trim((string) ($x ?? ''));
    return $x === '' ? $pad : $e($x);
};
/** Tabela chave/valor. */
$kv = static function (array $pares) use ($v): string {
    $linhas = '';
    foreach ($pares as $k => $val) {
        $linhas .= '<tr><th>' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '</th><td>' . $v($val) . '</td></tr>';
    }
    return '<table class="identificarcomputador-kv">' . $linhas . '</table>';
};
/** Tabela de lista (cabecalhos + linhas de arrays). */
$tab = static function (array $cols, $linhas) use ($e): string {
    $linhas = is_array($linhas) ? $linhas : [];
    if ($linhas === []) {
        return '<div class="identificarcomputador-vazio">Nenhum registro.</div>';
    }
    $h = '<table class="identificarcomputador-tabela identificarcomputador-tabela-det"><thead><tr>';
    foreach ($cols as $c) { $h .= '<th>' . $e($c['t']) . '</th>'; }
    $h .= '</tr></thead><tbody>';
    foreach ($linhas as $ln) {
        $h .= '<tr>';
        foreach ($cols as $c) {
            $val = $ln[$c['k']] ?? '';
            if (is_array($val)) { $val = implode(', ', array_map('strval', $val)); }
            $h .= '<td>' . ($val === '' || $val === null ? '-' : $e((string) $val)) . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table>';
};
$gb = static fn($bytes): string => PluginIdentificarcomputadorComputador::formatarBytes($bytes);
?>
<div class="identificarcomputador-painel identificarcomputador-detalhe">

  <div class="identificarcomputador-det-topo">
    <a class="btn identificarcomputador-btn" href="<?php echo $e($C::url('computador.php')); ?>"><i class="ti ti-arrow-left"></i> Voltar</a>
    <div class="identificarcomputador-det-nome"><i class="ti ti-device-desktop"></i> <?php echo $v($det['hostname']); ?></div>
    <?php if ($cfgExcluir = Session::haveRight('config', UPDATE)) { ?>
      <button type="button" class="btn identificarcomputador-btn-perigo" data-ic-excluir="<?php echo (int) $det['id']; ?>"><i class="ti ti-trash"></i> Excluir</button>
    <?php } ?>
  </div>

  <div class="identificarcomputador-abas" role="tablist">
    <button type="button" class="identificarcomputador-aba ativa" data-aba="resumo"><i class="ti ti-id"></i> Resumo</button>
    <button type="button" class="identificarcomputador-aba" data-aba="hardware"><i class="ti ti-cpu"></i> Hardware</button>
    <button type="button" class="identificarcomputador-aba" data-aba="rede"><i class="ti ti-network"></i> Rede e portas</button>
    <button type="button" class="identificarcomputador-aba" data-aba="seguranca"><i class="ti ti-shield"></i> Segurança</button>
    <button type="button" class="identificarcomputador-aba" data-aba="programas"><i class="ti ti-apps"></i> Programas</button>
    <button type="button" class="identificarcomputador-aba" data-aba="historico"><i class="ti ti-history"></i> Histórico</button>
  </div>

  <div class="identificarcomputador-aba-corpo ativa" data-corpo="resumo">
    <?php echo $kv([
      'Hostname'        => $d['hostname'] ?? '',
      'Usuário logado'  => $d['usuario_logado'] ?? '',
      'Domínio'         => $d['dominio'] ?? '',
      'Tipo'            => $d['tipo'] ?? '',
      'Sistema'         => trim(($d['so'] ?? '') . ' ' . ($d['so_arquitetura'] ?? '')),
      'Versão / build'  => trim(($d['so_versao'] ?? '') . ' (' . ($d['so_build'] ?? '') . ')', ' ()'),
      'Instalado em'    => $d['instalado_em'] ?? '',
      'Fabricante'      => $d['fabricante'] ?? '',
      'Modelo'          => $d['modelo'] ?? '',
      'Nº de série'     => $d['serial_maquina'] ?? '',
      'UUID'            => $d['uuid'] ?? '',
      'BIOS'            => trim(($d['bios_fabricante'] ?? '') . ' ' . ($d['bios_versao'] ?? '')),
      'Ligado há'       => isset($d['uptime_horas']) ? ($d['uptime_horas'] . ' h') : '',
      'Último boot'     => $d['ultimo_boot'] ?? '',
      'Coletas'         => (string) ($det['resumo']['qtd_coletas'] ?? ''),
      'Primeira coleta' => Html::convDateTime($det['resumo']['date_creation'] ?? ''),
      'Última coleta'   => Html::convDateTime($det['resumo']['date_mod'] ?? ''),
    ]); ?>
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="hardware">
    <div class="identificarcomputador-bloco-tit">Placa-mãe e processador</div>
    <?php echo $kv([
      'Placa-mãe'        => $d['placa_mae'] ?? '',
      'Nº série placa'   => $d['placa_mae_serial'] ?? '',
      'Processador'      => $d['processador'] ?? '',
    ]); ?>
    <?php echo $tab([['k'=>'nome','t'=>'CPU'],['k'=>'nucleos','t'=>'Núcleos'],['k'=>'logicos','t'=>'Lógicos'],['k'=>'clock_mhz','t'=>'Clock (MHz)'],['k'=>'socket','t'=>'Socket']], $d['processadores'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Memória (<?php echo $v($d['ram_total_gb'] ?? '', '?'); ?> GB)</div>
    <?php echo $tab([['k'=>'slot','t'=>'Slot'],['k'=>'capacidade_gb','t'=>'GB'],['k'=>'velocidade_mhz','t'=>'MHz'],['k'=>'fabricante','t'=>'Fabricante'],['k'=>'modelo','t'=>'Modelo'],['k'=>'serial','t'=>'Nº série']], $d['memoria'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Discos</div>
    <?php echo $tab([['k'=>'modelo','t'=>'Modelo'],['k'=>'serial','t'=>'Nº série'],['k'=>'tamanho_gb','t'=>'GB'],['k'=>'tipo','t'=>'Tipo'],['k'=>'interface','t'=>'Interface'],['k'=>'saude','t'=>'Saúde']], $d['discos'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Volumes</div>
    <?php echo $tab([['k'=>'letra','t'=>'Unidade'],['k'=>'rotulo','t'=>'Rótulo'],['k'=>'sistema_arquivos','t'=>'FS'],['k'=>'total_gb','t'=>'Total GB'],['k'=>'livre_gb','t'=>'Livre GB']], $d['volumes'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Vídeo</div>
    <?php echo $tab([['k'=>'nome','t'=>'Placa'],['k'=>'memoria_mb','t'=>'Memória (MB)'],['k'=>'resolucao','t'=>'Resolução'],['k'=>'driver','t'=>'Driver']], $d['placas_video'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Monitores</div>
    <?php echo $tab([['k'=>'fabricante','t'=>'Fabricante'],['k'=>'modelo','t'=>'Modelo'],['k'=>'serial','t'=>'Nº série'],['k'=>'ano','t'=>'Ano']], $d['monitores'] ?? []); ?>

    <div class="identificarcomputador-bloco-tit">Outros dispositivos</div>
    <?php echo $kv([
      'Som'        => $d['placas_som'] ?? '',
      'Teclado'    => $d['teclados'] ?? '',
      'Mouse'      => $d['mouses'] ?? '',
      'USB'        => $d['usb'] ?? '',
      'TPM'        => isset($d['tpm']['presente']) ? ($d['tpm']['presente'] ? ('Presente ' . ($d['tpm']['versao'] ?? '')) : 'Não presente') : '',
      'Bateria'    => isset($d['bateria']['carga']) ? ($d['bateria']['carga'] . '%') : '',
    ]); ?>
    <div class="identificarcomputador-subtit">Impressoras</div>
    <?php echo $tab([['k'=>'nome','t'=>'Nome'],['k'=>'porta','t'=>'Porta'],['k'=>'padrao','t'=>'Padrão'],['k'=>'compartilhada','t'=>'Compart.']], $d['impressoras'] ?? []); ?>
    <div class="identificarcomputador-subtit">Scanners</div>
    <?php echo $v($d['scanners'] ?? '', 'Nenhum'); ?>
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="rede">
    <div class="identificarcomputador-bloco-tit">Adaptadores</div>
    <?php echo $tab([['k'=>'nome','t'=>'Nome'],['k'=>'tipo','t'=>'Tipo'],['k'=>'mac','t'=>'MAC'],['k'=>'status','t'=>'Status'],['k'=>'velocidade','t'=>'Velocidade'],['k'=>'descricao','t'=>'Descrição']], $d['adaptadores_rede'] ?? []); ?>
    <div class="identificarcomputador-bloco-tit">Endereços IP</div>
    <?php echo $tab([['k'=>'adaptador','t'=>'Adaptador'],['k'=>'ipv4','t'=>'IPv4'],['k'=>'gateway','t'=>'Gateway'],['k'=>'dns','t'=>'DNS']], $d['ips_detalhe'] ?? []); ?>
    <div class="identificarcomputador-bloco-tit">Portas abertas e quem as usa</div>
    <?php echo $tab([['k'=>'protocolo','t'=>'Protocolo'],['k'=>'porta','t'=>'Porta'],['k'=>'endereco','t'=>'Endereço'],['k'=>'processo','t'=>'Processo'],['k'=>'pid','t'=>'PID'],['k'=>'caminho','t'=>'Caminho']], $d['portas'] ?? []); ?>
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="seguranca">
    <div class="identificarcomputador-bloco-tit">Antivírus</div>
    <?php echo $tab([['k'=>'nome','t'=>'Produto'],['k'=>'estado','t'=>'Estado']], $d['antivirus_lista'] ?? []); ?>
    <div class="identificarcomputador-bloco-tit">Firewall</div>
    <?php echo $tab([['k'=>'perfil','t'=>'Perfil'],['k'=>'ativo','t'=>'Ativo']], array_map(static fn($f) => ['perfil'=>$f['perfil'] ?? '', 'ativo'=>!empty($f['ativo'])?'Sim':'Não'], (array) ($d['firewall_perfis'] ?? []))); ?>
    <div class="identificarcomputador-bloco-tit">BitLocker</div>
    <?php echo $tab([['k'=>'unidade','t'=>'Unidade'],['k'=>'protecao','t'=>'Proteção'],['k'=>'status','t'=>'Status']], $d['bitlocker'] ?? []); ?>
    <div class="identificarcomputador-bloco-tit">Atualizações recentes</div>
    <?php echo $tab([['k'=>'id','t'=>'KB'],['k'=>'tipo','t'=>'Tipo'],['k'=>'instalado_em','t'=>'Instalado em']], $d['hotfix_recentes'] ?? []); ?>
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="programas">
    <div class="identificarcomputador-bloco-tit">Acesso remoto</div>
    <?php echo $tab([['k'=>'programa','t'=>'Programa'],['k'=>'id','t'=>'ID'],['k'=>'versao','t'=>'Versão']], $d['acesso_remoto_lista'] ?? []); ?>
    <?php if (!empty($d['acesso_remoto_instalados'])) { ?>
      <p class="identificarcomputador-ajuda"><i class="ti ti-info-circle"></i> Também instalados (sem ID): <?php echo $v($d['acesso_remoto_instalados']); ?></p>
    <?php } ?>
    <div class="identificarcomputador-bloco-tit">Programas instalados (<?php echo is_array($d['programas'] ?? null) ? count($d['programas']) : 0; ?>)</div>
    <?php echo $tab([['k'=>'nome','t'=>'Nome'],['k'=>'versao','t'=>'Versão'],['k'=>'fabricante','t'=>'Fabricante']], $d['programas'] ?? []); ?>
  </div>

  <div class="identificarcomputador-aba-corpo" data-corpo="historico">
    <div class="identificarcomputador-bloco-tit">Execuções deste computador</div>
    <?php echo $tab([['k'=>'data','t'=>'Data'],['k'=>'ip','t'=>'IP de origem']], $det['historico']); ?>
  </div>

</div>
<script>window.identificarcomputadorCfg = <?php echo json_encode(['url_ajax'=>$C::url('ajax.php'),'url_lista'=>$C::url('computador.php'),'csrf'=>Session::getNewCSRFToken(),'detalhe'=>true], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php
Html::footer();
