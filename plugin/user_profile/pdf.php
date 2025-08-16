<?php
use Chamilo\CoreBundle\Component\Utils\ChamiloApi;

require_once __DIR__.'/config.php';
if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}
require_once __DIR__.'/UserProfilePlugin.php';
require_once api_get_path(LIBRARY_PATH).'MyStudents.php';

$userId = (int) ($_GET['id'] ?? api_get_user_id());
$info = api_get_user_info($userId);
if (empty($info)) {
    api_not_allowed(true);
}

$plugin = UserProfilePlugin::create();
$urlId = api_get_current_access_url_id();
$tblField = Database::get_main_table(UserProfilePlugin::TABLE_FIELD);
$tblValue = Database::get_main_table(UserProfilePlugin::TABLE_VALUE);
$tblCat = Database::get_main_table(UserProfilePlugin::TABLE_CATEGORY);
$sql = "SELECT f.id, f.name, f.field_type, f.category_id, v.value, c.name AS category_name
        FROM $tblField f
        LEFT JOIN $tblValue v ON (f.id = v.field_id AND v.user_id = $userId)
        LEFT JOIN $tblCat c ON (f.category_id = c.id)
        WHERE f.access_url_id = $urlId AND c.access_url_id = $urlId
        ORDER BY f.field_order, f.id";
$result = Database::query($sql);
$fields = Database::store_result($result);
$fieldsByCat = [];
foreach ($fields as $field) {
    $fieldsByCat[$field['category_id']][] = $field;
}
$categories = $plugin->getCategories();

ob_start();
?>
<h2><?php echo get_lang('UserProfile'); ?></h2>
<div class="card user-profile mb-3">
    <div class="card-title"><strong><?php echo get_lang('PlatformFields', 'user_profile'); ?></strong></div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item"><strong><?php echo get_lang('FirstName'); ?>:</strong> <?php echo Security::remove_XSS($info['firstname']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('LastName'); ?>:</strong> <?php echo Security::remove_XSS($info['lastname']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('Email'); ?>:</strong> <?php echo Security::remove_XSS($info['email']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('OfficialCode'); ?>:</strong> <?php echo Security::remove_XSS($info['official_code']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('Phone'); ?>:</strong> <?php echo Security::remove_XSS($info['phone']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('RegistrationDate'); ?>:</strong> <?php echo Security::remove_XSS($info['registration_date']); ?></li>
        <li class="list-group-item"><strong><?php echo get_lang('LastLogins'); ?>:</strong> <?php echo Security::remove_XSS($info['last_login']); ?></li>
    </ul>
</div>
<?php foreach ($categories as $cat): ?>
<div class="card user-profile mb-3">
    <div class="card-title category-title"><strong><?php echo Security::remove_XSS(UserProfilePlugin::getCategoryLabel($cat)); ?></strong></div>
    <?php if (!empty($fieldsByCat[$cat['id']])): ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($fieldsByCat[$cat['id']] as $field): ?>
        <?php
        $val = $field['value'];
        if ($field['field_type'] === 'date' && !empty($val)) {
            $val = api_format_date($val, DATE_FORMAT_LONG);
        }
        ?>
        <li class="list-group-item"><strong><?php echo Security::remove_XSS($field['name']); ?>:</strong> <?php echo Security::remove_XSS($val); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php echo MyStudents::getBlockForSynthesis($userId, true); ?>
<?php
$html = ob_get_clean();

$logo = ChamiloApi::getPlatformLogoPath('', true);
$header = '<div style="text-align:right;"><img src="'.$logo.'" height="50"></div>';
$date = api_format_date(api_get_local_time(), DATE_TIME_FORMAT_LONG);
$footer = '<table width="100%"><tr><td>'.$date.'</td><td style="text-align:right">{PAGENO}/{nb}</td></tr></table>';

$pdf = new PDF();
$pdf->set_custom_header($header);
$pdf->set_custom_footer($footer);
$pdf->content_to_pdf($html, '', 'user_profile_'.$info['username']);
