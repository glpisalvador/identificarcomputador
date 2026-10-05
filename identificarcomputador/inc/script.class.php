<?php

/**
 * Geracao do script de coleta e gerencia dos codigos de download.
 *
 * O arquivo entregue e um .cmd que:
 *   1. se eleva a administrador (aviso padrao do Windows, o usuario escolhe "executar assim mesmo");
 *   2. roda por dentro um PowerShell que coleta as informacoes da maquina;
 *   3. envia um JSON para front/receber.php, autorizado por um codigo valido por 30 min.
 *
 * O PowerShell e embutido no .cmd em base64 (UTF-16LE), evitando problemas de escape
 * e o bloqueio de arquivos .ps1 baixados.
 */
class PluginIdentificarcomputadorScript extends CommonGLPI
{
    public const TABELA_TOKENS = 'glpi_plugin_identificarcomputador_tokens';

    public static function getTypeName($nb = 0): string
    {
        return __('Identificar Computador', 'identificarcomputador');
    }

    // ------------------------------------------------------------------ tokens

    /** Cria um codigo de download ligado ao usuario, valido pelos minutos configurados. */
    public static function gerarToken(int $users_id): string
    {
        global $DB;
        $token   = bin2hex(random_bytes(16));
        $minutos = PluginIdentificarcomputadorConfig::getTokenMinutos();
        $DB->insert(self::TABELA_TOKENS, [
            'token'          => $token,
            'users_id'       => $users_id,
            'date_creation'  => date('Y-m-d H:i:s'),
            'date_expiracao' => date('Y-m-d H:i:s', time() + $minutos * 60),
        ]);
        return $token;
    }

    /**
     * Valida um codigo recebido do script. Retorna o users_id de quem baixou ou null.
     * Permite varias execucoes dentro da janela; apenas marca o ultimo uso.
     */
    public static function validarToken(string $token, string $ip = ''): ?int
    {
        global $DB;
        $token = trim($token);
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }
        $row = $DB->request([
            'FROM'  => self::TABELA_TOKENS,
            'WHERE' => ['token' => $token],
            'LIMIT' => 1,
        ])->current();
        if (!$row) {
            return null;
        }
        if (strtotime((string) $row['date_expiracao']) < time()) {
            return null;
        }
        $DB->update(self::TABELA_TOKENS, [
            'usado_em'  => date('Y-m-d H:i:s'),
            'ip_origem' => substr($ip, 0, 100),
        ], ['id' => (int) $row['id']]);
        return (int) $row['users_id'];
    }

    /** Remove os codigos vencidos. Retorna quantos foram removidos. */
    public static function limparExpirados(): int
    {
        global $DB;
        $antes = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA_TOKENS])->current()['total'] ?? 0);
        $DB->delete(self::TABELA_TOKENS, ['date_expiracao' => ['<', date('Y-m-d H:i:s')]]);
        $depois = (int) ($DB->request(['COUNT' => 'total', 'FROM' => self::TABELA_TOKENS])->current()['total'] ?? 0);
        return max(0, $antes - $depois);
    }

    public static function cronInfo($name): array
    {
        return ['description' => __('Remove os codigos de download expirados', 'identificarcomputador')];
    }

    public static function cronLimparTokens(CronTask $task): int
    {
        $n = self::limparExpirados();
        if ($n > 0) {
            $task->addVolume($n);
        }
        return 1;
    }

    // ------------------------------------------------------------------ geracao do .cmd

    /** Monta o conteudo do arquivo .cmd pronto para download. */
    public static function gerarCmd(string $token): string
    {
        $url     = PluginIdentificarcomputadorConfig::getUrlRecebimento();
        $minutos = PluginIdentificarcomputadorConfig::getTokenMinutos();
        $destino = $url . '/plugins/identificarcomputador/front/receber.php';

        $ps = self::powershell();
        $ps = str_replace(['{{DESTINO}}', '{{TOKEN}}', '{{MINUTOS}}'], [$destino, $token, (string) $minutos], $ps);

        // UTF-16LE + base64 para o parametro -EncodedCommand do PowerShell
        $b64 = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));

        $cmd  = "@echo off\r\n";
        $cmd .= "title Identificar Computador - GLPI\r\n";
        $cmd .= "net session >nul 2>&1\r\n";
        $cmd .= "if %errorlevel% NEQ 0 (\r\n";
        $cmd .= "  echo Solicitando permissao de administrador...\r\n";
        $cmd .= "  powershell -NoProfile -Command \"Start-Process -Verb RunAs -FilePath '%~f0'\"\r\n";
        $cmd .= "  exit /b\r\n";
        $cmd .= ")\r\n";
        $cmd .= "powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand " . $b64 . "\r\n";
        $cmd .= "echo.\r\n";
        $cmd .= "pause\r\n";

        return $cmd;
    }

    /**
     * Script PowerShell de coleta. Placeholders {{DESTINO}}, {{TOKEN}} e {{MINUTOS}}
     * sao trocados antes de codificar. Cada secao e protegida por try/catch: uma falha
     * nunca interrompe a coleta.
     */
    private static function powershell(): string
    {
        return <<<'PS'
$ErrorActionPreference = 'SilentlyContinue'
$ProgressPreference = 'SilentlyContinue'
chcp 65001 > $null
$Host.UI.RawUI.WindowTitle = 'Identificar Computador - GLPI'

function Secao($t) { Write-Host ''; Write-Host ('>> ' + $t) -ForegroundColor Cyan }
function Linha($t) { Write-Host ('   ' + $t) -ForegroundColor Gray }

Write-Host '============================================================'
Write-Host '   Identificar Computador - coleta de informacoes' -ForegroundColor White
Write-Host '============================================================'
Linha 'Este processo apenas LE informacoes do computador e envia ao GLPI.'
Linha 'Nenhuma alteracao e feita na maquina.'

$d = [ordered]@{}
$d.coletado_em = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
$d.script_versao = '1.0.0'

Secao 'Identificacao'
try {
    $cs  = Get-CimInstance Win32_ComputerSystem
    $os  = Get-CimInstance Win32_OperatingSystem
    $bios= Get-CimInstance Win32_BIOS
    $csp = Get-CimInstance Win32_ComputerSystemProduct
    $d.hostname       = $env:COMPUTERNAME
    $d.dominio        = $cs.Domain
    $d.usuario_logado = if ($cs.UserName) { $cs.UserName } else { "$env:USERDOMAIN\$env:USERNAME" }
    $d.fabricante     = $cs.Manufacturer
    $d.modelo         = $cs.Model
    $d.tipo           = switch ([int]$cs.PCSystemType) { 1 {'Desktop'} 2 {'Notebook'} 3 {'Workstation'} 4 {'Servidor'} 5 {'Servidor'} 7 {'Servidor'} default {'Outro'} }
    $d.so             = $os.Caption
    $d.so_versao      = $os.Version
    $d.so_build       = $os.BuildNumber
    $d.so_arquitetura = $os.OSArchitecture
    $d.instalado_em   = ($os.InstallDate).ToString('yyyy-MM-dd')
    $d.ultimo_boot    = ($os.LastBootUpTime).ToString('yyyy-MM-dd HH:mm:ss')
    $d.uptime_horas   = [math]::Round(((Get-Date) - $os.LastBootUpTime).TotalHours, 1)
    $d.bios_versao    = $bios.SMBIOSBIOSVersion
    $d.bios_fabricante= $bios.Manufacturer
    $d.serial_maquina = $bios.SerialNumber
    $d.uuid           = $csp.UUID
    Linha ($d.hostname + ' - ' + $d.so)
} catch { Linha 'falha parcial na identificacao' }

Secao 'Placa-mae e processador'
try {
    $mb = Get-CimInstance Win32_BaseBoard
    $d.placa_mae         = ($mb.Manufacturer + ' ' + $mb.Product).Trim()
    $d.placa_mae_serial  = $mb.SerialNumber
} catch {}
try {
    $cpus = @()
    foreach ($c in Get-CimInstance Win32_Processor) {
        $cpus += [ordered]@{ nome = $c.Name.Trim(); nucleos = $c.NumberOfCores; logicos = $c.NumberOfLogicalProcessors; clock_mhz = $c.MaxClockSpeed; socket = $c.SocketDesignation }
    }
    $d.processadores = $cpus
    if ($cpus.Count -gt 0) { $d.processador = $cpus[0].nome }
} catch {}

Secao 'Memoria RAM'
try {
    $cs2 = Get-CimInstance Win32_ComputerSystem
    $d.ram_total_bytes = [int64]$cs2.TotalPhysicalMemory
    $d.ram_total_gb = [math]::Round($cs2.TotalPhysicalMemory / 1GB, 1)
    $pentes = @()
    foreach ($m in Get-CimInstance Win32_PhysicalMemory) {
        $pentes += [ordered]@{ fabricante = $m.Manufacturer; modelo = $m.PartNumber.Trim(); serial = $m.SerialNumber; capacidade_gb = [math]::Round($m.Capacity / 1GB, 1); velocidade_mhz = $m.Speed; slot = $m.DeviceLocator }
    }
    $d.memoria = $pentes
    Linha ([string]$d.ram_total_gb + ' GB em ' + $pentes.Count + ' pente(s)')
} catch {}

Secao 'Armazenamento'
try {
    $saude = @{}
    try { foreach ($pd in Get-PhysicalDisk) { $saude[[string]$pd.SerialNumber.Trim()] = $pd.HealthStatus } } catch {}
    $discos = @()
    $tot = 0
    foreach ($disk in Get-CimInstance Win32_DiskDrive) {
        $tot += [int64]$disk.Size
        $sn = ([string]$disk.SerialNumber).Trim()
        $discos += [ordered]@{ modelo = $disk.Model; serial = $sn; tamanho_gb = [math]::Round($disk.Size / 1GB, 0); tipo = $disk.MediaType; interface = $disk.InterfaceType; saude = $saude[$sn] }
    }
    $d.discos = $discos
    $d.armazenamento_total_bytes = $tot
    $volumes = @()
    foreach ($v in Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3') {
        $volumes += [ordered]@{ letra = $v.DeviceID; rotulo = $v.VolumeName; sistema_arquivos = $v.FileSystem; total_gb = [math]::Round($v.Size / 1GB, 1); livre_gb = [math]::Round($v.FreeSpace / 1GB, 1) }
    }
    $d.volumes = $volumes
} catch {}

Secao 'Video, som e perifericos'
try { $d.placas_video = @(Get-CimInstance Win32_VideoController | ForEach-Object { [ordered]@{ nome = $_.Name; memoria_mb = [math]::Round($_.AdapterRAM / 1MB, 0); driver = $_.DriverVersion; resolucao = ("$($_.CurrentHorizontalResolution)x$($_.CurrentVerticalResolution)") } }) } catch {}
try { if ($d.placas_video.Count -gt 0) { $d.placa_video = ($d.placas_video | ForEach-Object { $_.nome }) -join '; ' } } catch {}
try { $d.placas_som = @(Get-CimInstance Win32_SoundDevice | ForEach-Object { $_.Name }) } catch {}
try { $d.teclados = @(Get-CimInstance Win32_Keyboard | ForEach-Object { $_.Description }) } catch {}
try { $d.mouses = @(Get-CimInstance Win32_PointingDevice | ForEach-Object { $_.Description } | Where-Object { $_ }) } catch {}
try { $d.impressoras = @(Get-CimInstance Win32_Printer | ForEach-Object { [ordered]@{ nome = $_.Name; porta = $_.PortName; padrao = $_.Default; compartilhada = $_.Shared } }) } catch {}
try {
    $monitores = @()
    foreach ($mon in Get-CimInstance -Namespace root\wmi -ClassName WmiMonitorID) {
        $fab = -join ($mon.ManufacturerName | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        $nome = -join ($mon.UserFriendlyName | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        $ser = -join ($mon.SerialNumberID | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        $monitores += [ordered]@{ fabricante = $fab; modelo = $nome; serial = $ser; ano = $mon.YearOfManufacture }
    }
    $d.monitores = $monitores
} catch {}
try { $d.scanners = @(Get-CimInstance Win32_PnPEntity -Filter "PNPClass='Image'" | ForEach-Object { $_.Name } | Where-Object { $_ }) } catch {}
try { $d.usb = @(Get-PnpDevice -PresentOnly -Class USB -Status OK | Select-Object -ExpandProperty FriendlyName -Unique | Where-Object { $_ }) } catch {}
try { $b = Get-CimInstance Win32_Battery; if ($b) { $d.bateria = [ordered]@{ nome = $b.Name; carga = $b.EstimatedChargeRemaining; status = $b.BatteryStatus } } } catch {}
try { $tpm = Get-CimInstance -Namespace root\cimv2\security\microsofttpm -ClassName Win32_Tpm; if ($tpm) { $d.tpm = [ordered]@{ presente = $true; versao = $tpm.SpecVersion; ativado = $tpm.IsEnabled_InitialValue } } else { $d.tpm = [ordered]@{ presente = $false } } } catch { $d.tpm = [ordered]@{ presente = $false } }

Secao 'Rede'
try {
    $adaptadores = @()
    foreach ($a in Get-NetAdapter | Where-Object { $_.Status -ne 'Not Present' }) {
        $tipo = if ($a.InterfaceDescription -match 'wi-?fi|wireless|802\.11') { 'WiFi' } elseif ($a.MediaType -match '802\.3' -or $a.PhysicalMediaType -match '802\.3') { 'Cabeada' } else { 'Outro' }
        $adaptadores += [ordered]@{ nome = $a.Name; descricao = $a.InterfaceDescription; mac = $a.MacAddress; tipo = $tipo; status = $a.Status; velocidade = $a.LinkSpeed }
    }
    $d.adaptadores_rede = $adaptadores
    $d.macs = (($adaptadores | Where-Object { $_.mac } | ForEach-Object { $_.mac }) -join ', ')
} catch {}
try {
    $ips = @()
    foreach ($ic in Get-NetIPConfiguration | Where-Object { $_.NetAdapter.Status -eq 'Up' }) {
        $ip4 = ($ic.IPv4Address | ForEach-Object { $_.IPAddress }) -join ', '
        $ips += [ordered]@{ adaptador = $ic.InterfaceAlias; ipv4 = $ip4; gateway = ($ic.IPv4DefaultGateway.NextHop -join ', '); dns = (($ic.DNSServer | ForEach-Object { $_.ServerAddresses }) -join ', ') }
    }
    $d.ips_detalhe = $ips
    $d.ips = (($ips | Where-Object { $_.ipv4 } | ForEach-Object { $_.ipv4 }) -join ', ')
} catch {}

Secao 'Portas abertas e quem as usa'
try {
    $procs = @{}
    foreach ($p in Get-Process) { $procs[[int]$p.Id] = $p }
    $portas = @()
    foreach ($c in Get-NetTCPConnection -State Listen) {
        $pid2 = [int]$c.OwningProcess
        $pr = $procs[$pid2]
        $portas += [ordered]@{ protocolo = 'TCP'; porta = $c.LocalPort; endereco = $c.LocalAddress; pid = $pid2; processo = if ($pr) { $pr.ProcessName } else { '' }; caminho = if ($pr) { $pr.Path } else { '' } }
    }
    foreach ($c in Get-NetUDPEndpoint) {
        $pid2 = [int]$c.OwningProcess
        $pr = $procs[$pid2]
        $portas += [ordered]@{ protocolo = 'UDP'; porta = $c.LocalPort; endereco = $c.LocalAddress; pid = $pid2; processo = if ($pr) { $pr.ProcessName } else { '' }; caminho = if ($pr) { $pr.Path } else { '' } }
    }
    $d.portas = @($portas | Sort-Object { [int]$_.porta } -Unique)
    Linha ($d.portas.Count.ToString() + ' porta(s) em escuta')
} catch {}

Secao 'Seguranca'
try {
    $avs = @()
    foreach ($av in Get-CimInstance -Namespace root\SecurityCenter2 -ClassName AntiVirusProduct) {
        $hex = '{0:x6}' -f [int]$av.productState
        $prodflag = $hex.Substring(2,2)
        $defflag  = $hex.Substring(4,2)
        $ativo = ($prodflag -eq '10' -or $prodflag -eq '11')
        $estado = if (-not $ativo) { 'Desativado' } elseif ($defflag -eq '00') { 'Ativo e atualizado' } else { 'Ativo, desatualizado' }
        $avs += [ordered]@{ nome = $av.displayName; estado = $estado }
    }
    if ($avs.Count -eq 0) {
        try { $mp = Get-MpComputerStatus; if ($mp) { $avs += [ordered]@{ nome = 'Windows Defender'; estado = if ($mp.AntivirusEnabled) { if ($mp.AntivirusSignatureAge -le 7) { 'Ativo e atualizado' } else { 'Ativo, desatualizado' } } else { 'Desativado' } } } } catch {}
    }
    $d.antivirus_lista = $avs
    if ($avs.Count -gt 0) { $d.antivirus = ($avs | ForEach-Object { $_.nome }) -join '; '; $d.antivirus_estado = $avs[0].estado }
} catch {}
try {
    $fw = @()
    foreach ($f in Get-NetFirewallProfile) { $fw += [ordered]@{ perfil = $f.Name; ativo = [bool]$f.Enabled } }
    $d.firewall_perfis = $fw
    $ativos = ($fw | Where-Object { $_.ativo }).Count
    $d.firewall = if ($ativos -eq $fw.Count -and $fw.Count -gt 0) { 'Ativo' } elseif ($ativos -gt 0) { 'Parcial' } else { 'Desativado' }
} catch {}
try {
    $bl = @()
    foreach ($v in Get-BitLockerVolume) { $bl += [ordered]@{ unidade = $v.MountPoint; protecao = [string]$v.ProtectionStatus; status = [string]$v.VolumeStatus } }
    $d.bitlocker = $bl
} catch {}
try { $d.hotfix_recentes = @(Get-HotFix | Sort-Object InstalledOn -Descending | Select-Object -First 15 | ForEach-Object { [ordered]@{ id = $_.HotFixID; tipo = $_.Description; instalado_em = if ($_.InstalledOn) { $_.InstalledOn.ToString('yyyy-MM-dd') } else { '' } } }) } catch {}

Secao 'Programas de acesso remoto'
try {
    $remoto = @()
    # TeamViewer (registro)
    foreach ($k in 'HKLM:\SOFTWARE\TeamViewer','HKLM:\SOFTWARE\WOW6432Node\TeamViewer') {
        try { $tv = Get-ItemProperty -Path $k -ErrorAction Stop; if ($tv.ClientID) { $remoto += [ordered]@{ programa = 'TeamViewer'; id = [string]$tv.ClientID; versao = [string]$tv.Version } } } catch {}
    }
    # AnyDesk (--get-id)
    foreach ($exe in "$env:ProgramFiles(x86)\AnyDesk\AnyDesk.exe","$env:ProgramFiles\AnyDesk\AnyDesk.exe") {
        if (Test-Path $exe) { try { $id = (& $exe --get-id 2>$null | Select-Object -First 1); if ($id) { $remoto += [ordered]@{ programa = 'AnyDesk'; id = ([string]$id).Trim() } } } catch {}; break }
    }
    # RustDesk (--get-id ou config)
    foreach ($exe in "$env:ProgramFiles\RustDesk\rustdesk.exe","${env:ProgramFiles(x86)}\RustDesk\rustdesk.exe") {
        if (Test-Path $exe) { try { $id = (& $exe --get-id 2>$null | Select-Object -First 1); if ($id) { $remoto += [ordered]@{ programa = 'RustDesk'; id = ([string]$id).Trim() } } } catch {}; break }
    }
    # Outros instalados (so presenca, sem id)
    $conhecidos = 'AnyDesk','TeamViewer','RustDesk','AnyViewer','Supremo','Ammyy','UltraViewer','Chrome Remote Desktop','DWService','Splashtop','LogMeIn','VNC','Parsec'
    $d.acesso_remoto_lista = $remoto
    if ($remoto.Count -gt 0) { $d.acesso_remoto = ($remoto | ForEach-Object { $_.programa + ': ' + $_.id }) -join ' | ' }
    $d.acesso_remoto_conhecidos = $conhecidos
} catch {}

Secao 'Programas instalados'
try {
    $prog = @()
    $chaves = 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*','HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
    foreach ($item in Get-ItemProperty $chaves) {
        if ($item.DisplayName) { $prog += [ordered]@{ nome = $item.DisplayName; versao = [string]$item.DisplayVersion; fabricante = [string]$item.Publisher } }
    }
    $d.programas = @($prog | Sort-Object { $_.nome } -Unique)
    Linha ($d.programas.Count.ToString() + ' programa(s) instalado(s)')
    # Casa a lista de acesso remoto conhecido com o que esta instalado
    $achados = @()
    foreach ($nome in $d.acesso_remoto_conhecidos) { if ($d.programas | Where-Object { $_.nome -like ("*" + $nome + "*") }) { $achados += $nome } }
    $d.acesso_remoto_instalados = @($achados | Select-Object -Unique)
} catch {}

Secao 'Enviando ao GLPI'
Linha 'Os dados coletados estao sendo enviados ao GLPI...'
$json = $d | ConvertTo-Json -Depth 8 -Compress
$destino = '{{DESTINO}}?token={{TOKEN}}'
$ok = $false
try {
    $resp = Invoke-RestMethod -Uri $destino -Method Post -Body $json -ContentType 'application/json; charset=utf-8' -TimeoutSec 60
    if ($resp.success) { $ok = $true; Write-Host ''; Write-Host '   Enviado com sucesso!' -ForegroundColor Green; Linha ('Computador: ' + $resp.computador) }
    else { Write-Host ''; Write-Host ('   O GLPI recusou o envio: ' + $resp.message) -ForegroundColor Yellow }
} catch {
    Write-Host ''
    Write-Host '   Nao foi possivel enviar ao GLPI.' -ForegroundColor Red
    Linha ('Detalhe: ' + $_.Exception.Message)
    Linha 'Verifique se a maquina alcanca o endereco do GLPI e se o codigo'
    Linha ('ainda e valido (ele expira em {{MINUTOS}} minutos apos o download).')
}
Write-Host ''
Write-Host '============================================================'
if ($ok) { Write-Host '   Concluido. Pode fechar esta janela.' -ForegroundColor Green }
else { Write-Host '   Finalizado com erro. Pode fechar esta janela.' -ForegroundColor Yellow }
Write-Host '============================================================'
PS;
    }
}
