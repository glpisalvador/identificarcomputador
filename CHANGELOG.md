# Histórico de versões

O arquivo para download de cada versão está em [Releases](https://github.com/glpisalvador/identificarcomputador/releases).

## 1.0.0 — 2026-10-05

Primeira versão publicada.

- Script .cmd que coleta o inventário completo de uma máquina Windows (hardware, rede, portas e quem as usa, segurança, acesso remoto com IDs, programas) e envia ao GLPI, sem agente e sem o inventário nativo.
- Script elevado a administrador, com janela de andamento, válido por um tempo configurável (padrão 30 minutos) e autorizado por código único.
- Painel com cartões, gráficos, filtros, exportação Excel/CSV e página de detalhe em abas.
- Permissões por perfil: quem vê os resultados e quem baixa o script.
- A desinstalação mantém as tabelas.
