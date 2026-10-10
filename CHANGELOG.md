# Histórico de versões

O arquivo para download de cada versão está em [Releases](https://github.com/glpisalvador/identificarcomputador/releases).

## 1.2.1 — 2026-10-10

Correção de acentos na saída dos scripts.

- A saída dos scripts executados agora sai em UTF-8 (chcp 65001 + captura UTF-8), então acentos e cedilha aparecem corretos no log, em vez de caracteres embaralhados.
- Vale para .bat, .cmd, .ps1, .py, .vbs e .js. Para .ps1 com acentos, salve o arquivo em UTF-8.

## 1.2.0 — 2026-10-10

Duas abas, dois scripts e correção da execução remota.

- A página principal agora tem duas abas: **Computadores identificados** e **Execuções remotas**.
- **Dois scripts separados:** um que só identifica o computador e fecha; outro que abre a execução remota por **10 minutos** (ou até a janela ser fechada).
- **Correção importante:** o script enviado agora é realmente executado na máquina e sempre devolve o resultado ao GLPI. Antes, scripts com "pause" (ou que esperavam uma tecla) travavam para sempre e nada voltava. Agora cada execução roda com timeout, recebe EOF (não trava), e o GLPI é avisado se deu certo ou não.
- A **saída** do script (inclusive o que ele lê da máquina) é capturada e registrada no log, com o computador, o arquivo, o usuário do GLPI, status e código.

## 1.1.1 — 2026-10-10

Suporte ao tema escuro do GLPI.

- Todas as telas do plugin (painel, detalhe, execução remota, configuração) agora têm cores próprias para os temas escuros do GLPI (Auror dark e demais), com fundos, bordas, textos, tabelas, gráficos e campos legíveis no escuro, mantendo o tema claro como estava.

## 1.1.0 — 2026-10-05

Ponte de execução remota (temporária) e página de logs.

- Enquanto a janela do script fica aberta (até 30 minutos), a máquina abre um canal com o GLPI.
- Nova página **Execução remota** (em Ferramentas): lista as máquinas online, permite enviar vários arquivos (ps1, bat, cmd, vbs, js, py — até 100 MB cada, em pedaços) e escolher qual executar. Tudo roda com a permissão de administrador que o usuário deu ao abrir o script.
- Aba **Logs**: cada execução registra quem enviou (usuário do GLPI), o arquivo, formato, tamanho, status, código de saída, data/hora, duração e o computador, com a saída completa.
- Permissão própria por perfil ("enviar e executar scripts"), separada de ver e baixar.
- Nada fica instalado na máquina: o canal termina ao fechar a janela ou em 30 minutos.

## 1.0.2 — 2026-10-05

Página de detalhe modernizada e mais dados coletados.

- **Aba Resumo reformulada:** hostname, usuário logado, se o usuário é local ou de domínio, domínio, tipo, sistema, instalado em, placa-mãe, processador, memória, armazenamento, placa de vídeo, placa de rede, placa de som, MAC e IP — sempre os principais da máquina.
- **Nova aba Usuários:** usuário conectado, usuários locais, usuários que já usaram a máquina (local, domínio e rede, resolvendo os perfis) e membros dos grupos de acesso (inclui contas de domínio).
- **Cada tabela com dados** ganhou **busca em tempo real** (alcança todas as colunas e linhas, com contador) e **ordenação por clique no cabeçalho**.
- Coleta nova: tipo do usuário (local/domínio), placa de rede, MAC e IP principais, usuários locais e de domínio/rede.
- Visual mais moderno: cabeçalhos fixos, listas roláveis e melhor uso do espaço da página.

## 1.0.1 — 2026-10-05

Correção importante no script de coleta.

- O arquivo .cmd antes embutia o PowerShell numa única linha `-EncodedCommand`, que passava do limite de 8191 caracteres do cmd.exe e dava **"O sistema não pode executar o programa especificado"** no Windows, sem coletar nada.
- Agora o .cmd grava o PowerShell num arquivo temporário e o executa, sem linha de comando gigante. Testado de ponta a ponta no Windows 11 (coleta e envio ao GLPI com sucesso).

## 1.0.0 — 2026-10-05

Primeira versão publicada.

- Script .cmd que coleta o inventário completo de uma máquina Windows (hardware, rede, portas e quem as usa, segurança, acesso remoto com IDs, programas) e envia ao GLPI, sem agente e sem o inventário nativo.
- Script elevado a administrador, com janela de andamento, válido por um tempo configurável (padrão 30 minutos) e autorizado por código único.
- Painel com cartões, gráficos, filtros, exportação Excel/CSV e página de detalhe em abas.
- Permissões por perfil: quem vê os resultados e quem baixa o script.
- A desinstalação mantém as tabelas.
