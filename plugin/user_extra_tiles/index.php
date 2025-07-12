<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once __DIR__.'/src/UserExtraTiles.php';

$plugin = UserExtraTiles::create();

$fields = [];
if (!api_is_anonymous()) {
    $userId = api_get_user_id();
    $fields = $plugin->getFieldValues($userId);
}

// When called from plugin regions, $plugin_info is defined and the template
// engine will render the view. When accessed directly, display the template
// manually.
if (!isset($plugin_info)) {
    $tpl = new Template($plugin->get_lang('UserFields'));
    $tpl->assign('user_extra_tiles', ['fields' => $fields]);
    $tpl->display('plugin/user_extra_tiles/view/tiles.tpl');
    return;
}

$_template['fields'] = $fields;

