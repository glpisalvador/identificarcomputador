<?php

/**
 * Item de menu "Identificar computador" dentro de Ferramentas.
 */
class PluginIdentificarcomputadorMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return __('Identificar Computador', 'identificarcomputador');
    }

    public static function canView(): bool
    {
        return PluginIdentificarcomputadorConfig::podeVer();
    }

    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    public static function getMenuContent(): array
    {
        $menu = [];
        $menu['title'] = self::getTypeName();
        $menu['page']  = '/plugins/identificarcomputador/front/computador.php';
        $menu['icon']  = 'ti ti-device-desktop-search';
        $menu['links'] = [
            'search' => '/plugins/identificarcomputador/front/computador.php',
        ];
        return $menu;
    }
}
