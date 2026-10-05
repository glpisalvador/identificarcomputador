# Histórico de versões

O arquivo para download de cada versão está em [Releases](https://github.com/glpisalvador/identificarcomputador/releases).

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
