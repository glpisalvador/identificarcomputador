<?php

/**
 * Atalho para a pagina de configuracao (acessivel pelo icone do plugin em Configurar > Plugins).
 */

Session::checkLoginUser();
Html::redirect(PluginIdentificarcomputadorConfig::url('config.form.php'));
