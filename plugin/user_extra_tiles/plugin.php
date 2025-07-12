<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once __DIR__.'/src/UserExtraTiles.php';

$plugin_info = UserExtraTiles::create()->get_info();
$plugin_info['templates'] = ['view/tiles.tpl'];
