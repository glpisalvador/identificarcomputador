# Identificar Computador para GLPI

> Autor: **GLPI Salvador** · Licença: **GPLv3** · Compatível com GLPI **11.0.0 a 12.x**

**Identificar Computador** levanta o inventário de máquinas Windows **sem instalar agente** e **sem usar o inventário nativo do GLPI**. O usuário baixa um script pela página do plugin, executa como administrador, e o computador aparece num painel com filtros, gráficos e página de detalhe.

## Como funciona

1. Na página **Ferramentas → Identificar Computador**, o usuário autorizado clica em **"Identifique meu computador"** e baixa um arquivo `.cmd`.
2. Na máquina Windows, ele executa o arquivo. O script pede elevação de administrador (aviso padrão do Windows) e mostra uma janela com o andamento.
3. O script lê as informações do computador e **envia ao GLPI** os dados coletados. Nada é alterado na máquina.
4. O computador aparece no painel. Rodar de novo na mesma máquina atualiza o registro, sem duplicar, e guarda a execução no histórico.

O script baixado vale por um tempo configurável (padrão **30 minutos**). O envio é autorizado por um código único ligado a quem baixou; depois do prazo, o GLPI recusa.

## O que é coletado

- **Identificação:** hostname, domínio, usuário logado, Windows (versão e build), BIOS, fabricante, modelo e número de série.
- **Hardware:** placa-mãe (com nº de série), processador, memória por pente, discos (modelo, série, tamanho e saúde), volumes, placa de vídeo, som, monitores (com nº de série), teclado, mouse, impressoras, scanners, dispositivos USB, TPM e bateria.
- **Rede:** adaptadores cabeados e Wi-Fi, MAC, IP, gateway e DNS; **portas abertas com o programa e o processo que usa cada uma**.
- **Segurança:** antivírus e estado da proteção, firewall por perfil, BitLocker e atualizações recentes.
- **Programas:** acesso remoto com seus IDs (AnyDesk, TeamViewer, RustDesk e outros) e a lista de programas instalados.

## Painel

- Cartões de resumo (total, coletados hoje, com acesso remoto, sem antivírus).
- Gráficos por sistema, fabricante, antivírus e firewall.
- Filtros por sistema, fabricante, antivírus, firewall, domínio, período e "com acesso remoto", além de busca por hostname, usuário, IP, MAC e modelo.
- Tabela com ordenação, paginação e exportação para Excel e CSV.
- Página de detalhe de cada máquina, em abas: Resumo, Hardware, Rede e portas, Segurança, Programas e Histórico.

## Permissões

Na configuração do plugin (ícone em **Configurar → Plugins**) definem-se, por perfil, **quem vê os resultados** e **quem pode baixar o script**. Perfis com direito de configuração do GLPI sempre têm acesso.

## Observações

- O arquivo é um `.cmd` não assinado. Ao executar, o Windows mostra o aviso de proteção; o usuário escolhe executar assim mesmo.
- Funciona no Windows 10 e 11 e no Windows Server 2016 ou mais novo.
- Em servidores, o antivírus pode não aparecer pela central de segurança do Windows; nesse caso o plugin identifica o Defender diretamente.
- Os IDs de acesso remoto aparecem quando o programa está instalado e já gerou um ID.

---

## Download e instalação

1. Baixe o arquivo `identificarcomputador-X.Y.Z.zip` da **[última versão](../../releases/latest)**. Use o arquivo anexado à release, não o "Source code".
2. Descompacte dentro da pasta `plugins/` do GLPI. O resultado deve ser `plugins/identificarcomputador/setup.php`.
3. Ajuste o dono dos arquivos para o usuário do servidor web, por exemplo:
   ```bash
   chown -R www-data:www-data /var/www/glpi/plugins/identificarcomputador
   ```
4. No GLPI, vá em **Configurar → Plugins** e clique em **Instalar** e depois em **Ativar**. Pela linha de comando:
   ```bash
   php bin/console plugin:install identificarcomputador -u <usuário administrador>
   php bin/console plugin:activate identificarcomputador
   ```

A instalação cria as tabelas, as configurações padrão e as ações automáticas do plugin, e funciona num GLPI sem nada configurado antes.

### Atualização

Substitua a pasta `plugins/identificarcomputador` pela versão nova e rode **Instalar** de novo, ou `php bin/console plugin:install identificarcomputador -f`. Depois, ative o plugin. As tabelas e colunas novas são criadas sem perder os dados.

### Desinstalação

A desinstalação **não apaga as tabelas do plugin**: reinstalar recupera os dados.

## Versões

O histórico, com o que mudou em cada versão e o arquivo para download, está em **[Releases](../../releases)**. Cada versão entrou por um **[pull request](../../pulls?q=is%3Apr)**.

## Licença

Distribuído sob a **GNU General Public License v3.0**. Veja o arquivo [LICENSE](LICENSE).