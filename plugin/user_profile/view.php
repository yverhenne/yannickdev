<?php
require_once __DIR__.'/config.php';
if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}
require_once __DIR__.'/UserProfilePlugin.php';

global $htmlHeadXtra;
$htmlHeadXtra[] = '<style>
    .user-profile.card {
        border: 1px solid #eee;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .user-profile .card-title {
        font-weight: bold;
        text-align: center;
        background: #f7f7f7;
        margin: 0;
        padding: 10px;
    }
    .list-group-item {
        border: 0px solid #ddd;
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
$pdfLink = Display::url(
    Display::return_icon('export_pdf.png', get_lang('ExportToPdf')),
    $pdfUrl
);
$backLink = '';
if (!empty($_GET['from_search'])) {
    $backLink = Display::url(
        Display::return_icon('back.png', get_lang('Back')),
        UserProfilePlugin::create()->getAdminUrl(),
        ['class' => 'mr-2']
    );
}
echo '<div class="d-flex justify-content-between align-items-center">';
echo '<h2>'.get_lang('UserProfile').'</h2>';
echo '<div>'.$backLink.$pdfLink.'</div>';
echo '</div>';

// Built-in fields
$built = [
    get_lang('FirstName') => $info['firstname'],
    get_lang('LastName') => $info['lastname'],
    get_lang('Email') => $info['email'],
    get_lang('OfficialCode') => $info['official_code'],
    get_lang('Phone') => $info['phone'],
    get_lang('RegistrationDate') => $info['registration_date'],
    get_lang('LastLogin') => $info['last_login'],
];
echo '<div class="card user-profile mb-3">';
echo '<div class="card-title"><strong>'.get_plugin_lang('PlatformFields', 'user_profile').'</strong></div>';
echo '<ul class="list-group list-group-flush">';
foreach ($built as $name => $value) {
    echo '<li class="list-group-item"><strong>'.$name.':</strong> '.Security::remove_XSS($value).'</li>';
}
echo '</ul></div>';

foreach ($fields as $field) {
    $fieldsByCat[$field['category_id']][] = $field;
}

$categories = UserProfilePlugin::create()->getCategories();
echo '<div class="row">';
foreach ($categories as $cat) {
    $catId = $cat['id'];
$label = UserProfilePlugin::getCategoryLabel($cat);
    echo '<div class="col-md-6">';
    echo '<div class="card user-profile mb-3">';
    echo '<div class="card-title"><strong>'.$label.'</strong></div>';
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
    echo '</div></div>';
}
echo '</div>';

Display::display_footer();
