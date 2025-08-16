<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/UserProfilePlugin.php';
require_once api_get_path(LIBRARY_PATH).'MyStudents.php';

if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}

global $htmlHeadXtra;
$htmlHeadXtra[] = '<script src="'.api_get_path(WEB_PUBLIC_PATH)
    .'assets/jquery.easy-pie-chart/dist/jquery.easypiechart.js"></script>';
$htmlHeadXtra[] = '<style>
    .user-profile.card {
        border: 1px solid #eee;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        margin-top: 10px;
    }
    .user-profile .card-title {
        font-weight: bold;
        text-align: center;
        background: #E1F0F5;
        margin: 0;
        padding: 10px;
    }
    .user-profile .card-title.category-title {
        background: #E1F0F5;
    }
    .list-group-item {
    border: 0px solid #ddd;
    font-size: 14px;
    }
</style>';


$userId = (int) ($_GET['id'] ?? api_get_user_id());
$info = api_get_user_info($userId);
if (empty($info)) {
    api_not_allowed(true);
}
$tblField = Database::get_main_table(UserProfilePlugin::TABLE_FIELD);
$tblValue = Database::get_main_table(UserProfilePlugin::TABLE_VALUE);
$tblCat = Database::get_main_table(UserProfilePlugin::TABLE_CATEGORY);
$urlId = api_get_current_access_url_id();
$sql = "SELECT f.id, f.name, f.field_type, f.category_id, v.value, c.name AS category_name
        FROM $tblField f
        LEFT JOIN $tblValue v ON (f.id = v.field_id AND v.user_id = $userId)
        LEFT JOIN $tblCat c ON (f.category_id = c.id)
        WHERE f.access_url_id = $urlId AND c.access_url_id = $urlId
        ORDER BY f.field_order, f.id";
$result = Database::query($sql);
$fields = Database::store_result($result);
$fieldsByCat = [];

Display::display_header(get_lang('UserProfile'));

$pdfUrl = api_get_path(WEB_PLUGIN_PATH).'user_profile/pdf.php?id='.$userId;
$xlsUrl = api_get_path(WEB_PLUGIN_PATH).'user_profile/xls.php?id='.$userId;
$pdfLink = '<a href="'.$pdfUrl.'" class="mr-2">'
    .Display::return_icon('icons\\32\\export_pdf.png', get_lang('ExportToPdf')).'</a>';
$xlsLink = '<a href="'.$xlsUrl.'">'
    .Display::return_icon('icons\\32\\export_excel.png', get_lang('ExportAsXLS')).'</a>';
$backLink = '<a href="javascript:history.back();" class="mr-2">'
    .Display::return_icon('back.png', get_lang('Back')).'</a>';
$editUrl = api_get_path(WEB_CODE_PATH).'admin/user_edit.php?user_id='.$userId;
$editLink = '<a href="'.$editUrl.'" class="mr-2">'
    .Display::return_icon('icons\\32\\edit.png', get_lang('Edit')).'</a>';
echo '<div class="mb-2">';
echo $backLink.$editLink.$pdfLink.$xlsLink;
echo '</div>';

// Built-in fields
$built = [
    get_lang('FirstName') => $info['firstname'],
    get_lang('LastName') => $info['lastname'],
    get_lang('Email') => $info['email'],
    get_lang('OfficialCode') => $info['official_code'],
    get_lang('Phone') => $info['phone'],
    get_lang('RegistrationDate') => $info['registration_date'],
    get_lang('LastLogins') => $info['last_login'],
];
echo '<div class="card user-profile mb-3">';
echo '<div class="card-title"><strong>'.get_lang('PlatformFields', 'user_profile').'</strong></div>';
echo '<ul class="list-group list-group-flush">';
foreach ($built as $name => $value) {
    echo '<li class="list-group-item"><strong>'.$name.':</strong> '.Security::remove_XSS($value).'</li>';
}
echo '</ul></div>';

foreach ($fields as $field) {
    $fieldsByCat[$field['category_id']][] = $field;
}
$categories = UserProfilePlugin::create()->getCategories();
foreach ($categories as $cat) {
    $catId = $cat['id'];
    $label = UserProfilePlugin::getCategoryLabel($cat);
    echo '<div class="card user-profile mb-3">';
    echo '<div class="card-title category-title"><strong>'.$label.'</strong></div>';
    if (!empty($fieldsByCat[$catId])) {
        echo '<ul class="list-group list-group-flush">';
        foreach ($fieldsByCat[$catId] as $field) {
            $val = $field['value'];
            if ($field['field_type'] === 'date' && !empty($val)) {
                $val = api_format_date($val, DATE_FORMAT_LONG);
            }
            echo '<li class="list-group-item"><strong>'.$field['name'].':</strong> '.Security::remove_XSS($val).'</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

echo MyStudents::getBlockForSynthesis($userId);

Display::display_footer();
?>
