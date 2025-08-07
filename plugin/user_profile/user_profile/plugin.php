<?php
/* For licensing terms, see /license.txt */
/**
 *
 * @author Yannick Verhenne
 *
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/UserProfilePlugin.php';

$plugin_info = UserProfilePlugin::create()->get_info();
?>