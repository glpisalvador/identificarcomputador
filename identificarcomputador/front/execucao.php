<?php

/**
 * A execucao remota agora e a segunda aba de computador.php.
 * Mantido apenas como atalho para nao quebrar links antigos.
 */

Session::checkLoginUser();
Html::redirect(PluginIdentificarcomputadorConfig::url('computador.php') . '?aba=execucoes');
