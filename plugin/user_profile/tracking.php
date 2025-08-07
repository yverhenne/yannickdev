<?php
require_once __DIR__.'/config.php';
if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}
require_once __DIR__.'/UserProfilePlugin.php';
require_once api_get_path(LIBRARY_PATH).'sessionmanager.lib.php';
require_once api_get_path(LIBRARY_PATH).'tracking.lib.php';

global $htmlHeadXtra;
$htmlHeadXtra[] = '<style>
    .user-profile.card {border:1px solid #eee;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,0.05);margin-bottom:20px;}
    .user-profile .card-title {font-weight:bold;text-align:center;background:#f7f7f7;margin:0;padding:10px;}
    .list-group-item {border:0px solid #ddd;}
    .list-group-item.active{background:#337ab7;border-color:#337ab7;}
    .progress-circle{--p:0%;width:80px;height:80px;border-radius:50%;background:conic-gradient(#28a745 var(--p),#e9ecef 0);display:flex;align-items:center;justify-content:center;font-weight:bold;}
    .time-block{background:#f8f9fa;border:1px solid #eee;border-radius:4px;padding:10px;text-align:center;margin-top:10px;margin-bottom:10px;}
</style>';

api_protect_admin_script();
$plugin = UserProfilePlugin::create();

$check = Security::check_token('post');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $check) {
    if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $fieldId = (int) ($_POST['field_id'] ?? 0);
        $checked = (int) ($_POST['checked'] ?? 0);
        $tblValue = Database::get_main_table(UserProfilePlugin::TABLE_VALUE);
        $where = ['user_id = ? AND field_id = ?' => [$userId, $fieldId]];
        $exists = Database::select('id', $tblValue, ['where' => $where], 'first');
        if ($exists) {
            Database::update($tblValue, ['checked' => $checked], $where);
        } else {
            Database::insert($tblValue, [
                'user_id' => $userId,
                'field_id' => $fieldId,
                'value' => '',
                'checked' => $checked,
            ]);
        }
    }
    Security::clear_token();
    header('Content-Type: application/json');
    echo json_encode(['token' => Security::get_token()]);
    exit;
}
$token = Security::get_token();

$tblUser = Database::get_main_table(TABLE_MAIN_USER);
$users = Database::query("SELECT id, firstname, lastname, email, official_code, phone, registration_date, last_login FROM $tblUser ORDER BY lastname, firstname");

Display::display_header(get_lang('UserTracking', 'user_profile'));

echo '<div class="row">';
// Calculate last week's start (Monday) and end (Sunday) in UTC
$start = new DateTime('monday last week', new DateTimeZone('UTC'));
$start->setTime(0, 0, 0);
$end = clone $start;
$end->modify('+6 days')->setTime(23, 59, 59);
$startUtc = api_get_utc_datetime($start->format('Y-m-d H:i:s'));
$endUtc = api_get_utc_datetime($end->format('Y-m-d H:i:s'));

while ($user = Database::fetch_array($users)) {
    $userId = (int) $user['id'];
    echo '<div class="col-md-6">';
    echo '<div class="card user-profile">';
    echo '<div class="card-title"><strong>'.Security::remove_XSS($user['firstname'].' '.$user['lastname']).'</strong></div>';
    echo '<div class="card-body"><div class="row">';

    echo '<div class="col-sm-8">';
    echo '<ul class="list-group list-group-flush">';
    echo '<li class="list-group-item"><strong>'.get_lang('Email').':</strong> '.Security::remove_XSS($user['email']).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('OfficialCode').':</strong> '.Security::remove_XSS($user['official_code']).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('Phone').':</strong> '.Security::remove_XSS($user['phone']).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('RegistrationDate').':</strong> '.Security::remove_XSS($user['registration_date']).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('LastLogins').':</strong> '.Security::remove_XSS($user['last_login']).'</li>';
    echo '</ul>';

    // Time spent last week
    $tblTrackCourseAccess = Database::get_main_table(TABLE_STATISTIC_TRACK_E_COURSE_ACCESS);
    $sqlTime = "SELECT SUM(UNIX_TIMESTAMP(logout_course_date) - UNIX_TIMESTAMP(login_course_date)) AS time
        FROM $tblTrackCourseAccess
        WHERE login_course_date >= '$startUtc'
          AND login_course_date <= '$endUtc'
          AND logout_course_date >= '$startUtc'
          AND logout_course_date <= '$endUtc'
          AND user_id = $userId";
    $resTime = Database::query($sqlTime);
    $rowTime = Database::fetch_array($resTime, 'ASSOC');
    $timeSpent = (int) $rowTime['time'];
    $timeLabel = get_lang('TimeSpentLastWeek', 'user_profile');
    echo '<div class="time-block"><strong>'.Security::remove_XSS($timeLabel).':</strong> '.Security::remove_XSS(gmdate('H:i:s', $timeSpent)).'</div>';
    echo '</div>'; // col-sm-8

    // Average progress circle
    $sessions = SessionManager::get_sessions_by_user($userId);
    $overall = 0;
    $count = 0;
    foreach ($sessions as $session) {
        $sessionId = (int) $session['session_id'];
        $courses = SessionManager::get_course_list_by_session_id($sessionId);
        $progressTotal = 0;
        $courseCount = 0;
        foreach ($courses as $course) {
            $progressTotal += Tracking::get_avg_student_progress($userId, $course['course_code'], [], $sessionId);
            $courseCount++;
        }
        $sessionProgress = $courseCount ? round($progressTotal / $courseCount) : 0;
        $overall += $sessionProgress;
        $count++;
    }
    $avg = $count ? round($overall / $count) : 0;
    echo '<div class="col-sm-4 d-flex flex-column align-items-center justify-content-center">';
    echo '<div class="progress-circle mb-2" style="--p:'.$avg.'%"><span>'.$avg.'%</span></div>';
    echo '<div>'.Security::remove_XSS(get_lang('AverageProgress', 'user_profile')).'</div>';
    echo '</div>'; // col-sm-4

    echo '</div>'; // row

    // Tracked custom fields by category
    $tblField = Database::get_main_table(UserProfilePlugin::TABLE_FIELD);
    $tblValue = Database::get_main_table(UserProfilePlugin::TABLE_VALUE);
    $tblCat = Database::get_main_table(UserProfilePlugin::TABLE_CATEGORY);
    $urlId = api_get_current_access_url_id();
    $sql = "SELECT f.id, f.name, f.field_type, f.category_id, v.value, COALESCE(v.checked,0) AS checked, c.name AS category_name
            FROM $tblField f
            LEFT JOIN $tblValue v ON (f.id = v.field_id AND v.user_id = $userId)
            LEFT JOIN $tblCat c ON (f.category_id = c.id)
            WHERE f.access_url_id = $urlId AND c.access_url_id = $urlId AND f.include_tracking = 1
            ORDER BY c.cat_order, f.field_order, f.id";
    $res = Database::query($sql);
    $fields = Database::store_result($res);
    $catFields = [];
    foreach ($fields as $field) {
        $catFields[$field['category_id']]['label'] = UserProfilePlugin::getCategoryLabel(['name' => $field['category_name']]);
        $catFields[$field['category_id']]['fields'][] = $field;
    }
    if (!empty($catFields)) {
        foreach ($catFields as $cat) {
            echo '<div class="mt-3">';
            echo '<div class="list-group-item active">'.Security::remove_XSS($cat['label']).'</div>';
            echo '<div class="table-responsive"><table class="table table-hover mb-0">';
            echo '<thead><tr><th></th><th class="text-right">'.get_lang('Completed', 'user_profile').'</th></tr></thead><tbody>';
            foreach ($cat['fields'] as $field) {
                $val = $field['value'];
                if ($field['field_type'] === 'date' && !empty($val)) {
                    $val = api_format_date($val, DATE_FORMAT_LONG);
                }
                echo '<tr>';
                $checkedAttr = !empty($field['checked']) ? ' checked' : '';
                echo '<tr>';
echo '<td>'.Security::remove_XSS($field['name']);
if (!empty($val)) {
    echo ' : ' . Security::remove_XSS($val);
}
echo '</td>';
echo '<td class="text-right"><input type="checkbox" class="track-check" data-user="'.$userId.'" data-field="'.$field['id'].'"'.$checkedAttr.'></td>';
echo '</tr>';

                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div>';
        }
        echo '</ul>';
    }
    echo '</div>'; // card-body
    echo '</div></div>';
}
echo '</div>';

echo "<script>
    var token = '$token';
    $(function(){
        $('.track-check').on('change', function(){
            var el = $(this);
            $.post('".api_get_self()."', {
                sec_token: token,
                action: 'toggle',
                user_id: el.data('user'),
                field_id: el.data('field'),
                checked: el.is(':checked') ? 1 : 0
            }, function(resp){ if(resp.token){ token = resp.token; } }, 'json');
        });
    });
</script>";

Display::display_footer();