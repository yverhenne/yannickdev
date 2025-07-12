<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once __DIR__.'/src/UserExtraTiles.php';

api_protect_admin_script();

$plugin = UserExtraTiles::create();
$token = Security::get_token('uetile');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
    if (isset($_POST['add'])) {
        $plugin->createField($_POST['variable'], $_POST['display_text'], (int)$_POST['field_order']);
        Security::clear_token('uetile');
        header('Location: '.api_get_self());
        exit;
    }
    if (isset($_POST['save_order']) && isset($_POST['order'])) {
        foreach ($_POST['order'] as $id => $order) {
            $plugin->updateOrder($id, (int)$order);
        }
        Security::clear_token('uetile');
        header('Location: '.api_get_self());
        exit;
    }
}

if (isset($_GET['delete'])) {
    if (Security::check_token('get', null, 'uetile')) {
        $plugin->deleteField((int)$_GET['delete']);
        Security::clear_token('uetile');
        header('Location: '.api_get_self());
        exit;
    }
}

$fields = $plugin->getFields();

$formAdd = new FormValidator('add_field', 'post', api_get_self());
$formAdd->addText('variable', get_lang('Variable'));
$formAdd->addText('display_text', get_lang('DisplayName'));
$formAdd->addText('field_order', get_lang('Position'), ['value' => count($fields)+1]);
$formAdd->addButton('add', get_lang('Add'));
$formAdd->addHidden('sec_token', $token);

$addForm = $formAdd->returnForm();

$content = '<h2>'.get_lang('UserFields').'</h2>';
$content .= $addForm;

$content .= '<form method="post" action="'.api_get_self().'">';
$content .= '<table class="table table-bordered">';
$content .= '<tr><th>'.get_lang('Name').'</th><th>'.get_lang('Position').'</th><th></th></tr>';
foreach ($fields as $field) {
    $content .= '<tr>';
    $content .= '<td>'.Security::remove_XSS($field['display_text']).'</td>';
    $content .= '<td><input type="text" name="order['.$field['id'].']" value="'.intval($field['field_order']).'" class="form-control" style="width:80px" /></td>';
    $content .= '<td><a class="btn btn-danger" href="'.api_get_self().'?delete='.$field['id'].'&sec_token='.$token.'" onclick="return confirm(\''.addslashes(get_lang('ConfirmYourChoice')).'\');">'.get_lang('Delete').'</a></td>';
    $content .= '</tr>';
}
$content .= '</table>';
$content .= '<input type="hidden" name="sec_token" value="'.$token.'" />';
$content .= '<button class="btn btn-primary" name="save_order" value="1">'.get_lang('Save').'</button>';
$content .= '</form>';

$tpl = new Template(get_lang('UserFields'), false, false, false, false, false);
$tpl->assign('content', $content);
$tpl->display_one_col_template();
