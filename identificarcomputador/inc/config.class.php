<?php

/**
 * Configuracao do plugin Identificar Computador.
 *
 * Guarda tudo numa tabela chave/valor. Dois conjuntos de perfis:
 *   allowed_profiles_ver    - quem ve os resultados (painel e detalhe)
 *   allowed_profiles_baixar - quem pode baixar o script
 * Mais: token_minutos (validade do codigo de download) e url_recebimento.
 */
class PluginIdentificarcomputadorConfig extends CommonDBTM
{
    // $rightname nao e redeclarada: e tipada (string) no GLPI 12 e sem tipo no GLPI 11,
    // e o PHP exige o mesmo tipo da classe pai. As permissoes usam Session::haveRight('config', ...).

    public const TABELA = 'glpi_plugin_identificarcomputador_configs';

    public static function getTypeName($nb = 0): string
    {
        return __('Identificar Computador', 'identificarcomputador');
    }

    /** Escape HTML seguro para uso nas views. */
    public static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    public static function getConfig(string $name, $default = null)
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::TABELA,
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1,
        ])->current();
        return $row ? $row['value'] : $default;
    }

    public static function setConfig(string $name, $value): bool
    {
        global $DB;
        $existe = $DB->request([
            'COUNT' => 'total',
            'FROM'  => self::TABELA,
            'WHERE' => ['name' => $name],
        ])->current();
        if ((int) ($existe['total'] ?? 0) > 0) {
            return (bool) $DB->update(self::TABELA, ['value' => $value], ['name' => $name]);
        }
        return (bool) $DB->insert(self::TABELA, ['name' => $name, 'value' => $value]);
    }

    public static function getArrayConfig(string $name): array
    {
        $v = self::getConfig($name, '[]');
        $a = json_decode((string) $v, true);
        return is_array($a) ? $a : [];
    }

    public static function setArrayConfig(string $name, array $value): bool
    {
        return self::setConfig($name, json_encode(array_values($value)));
    }

    public static function getAllConfigs(): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => self::TABELA]) as $row) {
            $out[$row['name']] = $row['value'];
        }
        return $out;
    }

    /** Minutos de validade do codigo de download (padrao 30, limite 1 a 1440). */
    public static function getTokenMinutos(): int
    {
        $m = (int) self::getConfig('token_minutos', 30);
        if ($m < 1)    { $m = 30; }
        if ($m > 1440) { $m = 1440; }
        return $m;
    }

    /**
     * URL base que a maquina usa para enviar os dados. Se nada estiver configurado,
     * usa a URL do proprio GLPI ($CFG_GLPI['url_base']).
     */
    public static function getUrlRecebimento(): string
    {
        global $CFG_GLPI;
        $u = trim((string) self::getConfig('url_recebimento', ''));
        if ($u === '') {
            $u = (string) ($CFG_GLPI['url_base'] ?? '');
        }
        return rtrim($u, '/');
    }

    /** URL de uma pagina do plugin (relativa ao GLPI). */
    public static function url(string $arquivo, array $params = []): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/identificarcomputador/front/' . $arquivo . ($params ? '?' . http_build_query($params) : '');
    }

    /** URL de um asset do plugin, com cache-buster pela versao. */
    public static function asset(string $caminho): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/identificarcomputador/' . ltrim($caminho, '/') . '?v=' . PLUGIN_IDENTIFICARCOMPUTADOR_VERSION;
    }

    /** Todos os perfis do GLPI (glpi_profiles nao tem is_deleted). */
    public static function getTodosPerfis(): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'ORDER' => 'name ASC']) as $row) {
            $out[(int) $row['id']] = $row['name'];
        }
        return $out;
    }

    private static function perfilAtual(): int
    {
        return (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
    }

    /** O perfil ativo pode ver os resultados? Config nativa de GLPI sempre pode. */
    public static function podeVer(): bool
    {
        if (Session::haveRight('config', UPDATE)) {
            return true;
        }
        return in_array(self::perfilAtual(), array_map('intval', self::getArrayConfig('allowed_profiles_ver')), true);
    }

    /** O perfil ativo pode baixar o script? */
    public static function podeBaixar(): bool
    {
        if (Session::haveRight('config', UPDATE)) {
            return true;
        }
        return in_array(self::perfilAtual(), array_map('intval', self::getArrayConfig('allowed_profiles_baixar')), true);
    }
}
