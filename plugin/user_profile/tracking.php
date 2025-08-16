<?php
require_once __DIR__.'/config.php';
if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}
require_once __DIR__.'/UserProfilePlugin.php';
require_once api_get_path(LIBRARY_PATH).'sessionmanager.lib.php';
require_once api_get_path(LIBRARY_PATH).'tracking.lib.php';

global $htmlHeadXtra;
$token = Security::get_token();
$htmlHeadXtra[] = '<style>
    .user-profile.card {border:1px solid #eee;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,0.05);margin-bottom:20px;}
    .user-profile .card-title {font-weight:bold;text-align:center;background:#f7f7f7;margin:0;padding:10px;}
    .list-group-item {border:0px solid #ddd;}
    .list-group-item.active{background:#337ab7;border-color:#337ab7;}
    .progress-circle{--p:0%;width:80px;height:80px;border-radius:50%;background:conic-gradient(#28a745 var(--p),#e9ecef 0);display:flex;align-items:center;justify-content:center;font-weight:bold;}
    .time-block{background:#f8f9fa;border:1px solid #eee;border-radius:4px;padding:10px;margin-top:10px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;}
    .user-profile-section {border:1px solid #eee;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,0.05);padding:15px;margin-bottom:20px;}
    .search-form {max-width:500px;margin:0 auto;}
    .search-form .search-input{width:100%;margin:10px 0;}
</style>';
$htmlHeadXtra[] = '<script>
var userProfileToken = "'.$token.'";
$(function(){
    $(document).on("click", ".warn-btn", function(e){
        e.preventDefault();
        var $card = $(this).closest(".user-profile");
        var userId = $card.data("user-id");
        var teacherId = $card.find(".teacher-select").val();
        if(!teacherId){
            alert("Veuillez sélectionner un formateur");
            return;
        }
        $.post("'.api_get_path(WEB_PLUGIN_PATH).'user_profile/ajax.php", {
            action: "warn",
            user_id: userId,
            teacher_id: teacherId,
            sec_token: userProfileToken
        }, function(resp){
            if (resp && resp.token) {
                userProfileToken = resp.token;
            }
            if (resp && resp.status === \'ok\') {
                alert("Message envoyé");
            } else {
                alert("Erreur lors de l\'envoi du message");
            }
        }, "json");
    });

    $(document).on("click", ".agenda-remind-btn", function(e){
        e.preventDefault();
        var $card = $(this).closest(".user-profile");
        var userId = $card.data("user-id");
        $.post("'.api_get_path(WEB_PLUGIN_PATH).'user_profile/ajax.php", {
            action: "remind_agenda",
            user_id: userId,
            sec_token: userProfileToken
        }, function(resp){
            if (resp && resp.token) {
                userProfileToken = resp.token;
            }
            if (resp && resp.status === \'ok\') {
                alert("Message envoyé");
            } else {
                alert("Erreur lors de l\'envoi du message");
            }
        }, "json");
    });

    $(document).on("change", ".per-page-select", function(){
        $("#per-page-form").submit();
    });
});
</script>';

api_protect_admin_script();
$plugin = UserProfilePlugin::create();
$urlId = api_get_current_access_url_id();

$tblUser = Database::get_main_table(TABLE_MAIN_USER);

$dateDisplayFormat = '%A %d %B %Y';

$search = trim($_GET['search'] ?? '');
$perPageOptions = [10, 20, 30, 50, 'all'];
$perPage = $_GET['per_page'] ?? 10;
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
// Collect IDs matching search if any
$searchResults = [];
if (strlen($search) >= 3) {
    $tblUrl = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
    $escaped = Database::escape_string($search);
    $condition = "(u.firstname LIKE '%$escaped%' OR u.lastname LIKE '%$escaped%')";
    if (api_is_multiple_url_enabled()) {
        $sql = "SELECT u.id FROM $tblUser u INNER JOIN $tblUrl url ON (u.id = url.user_id) WHERE url.access_url_id = $urlId AND $condition";
    } else {
        $sql = "SELECT id FROM $tblUser u WHERE $condition";
    }
    $res = Database::query($sql);
    $searchResults = Database::store_result($res);
}

// Fetch users to display (all or search-filtered)
$userSql = "SELECT id, firstname, lastname, email, phone, registration_date, last_login FROM $tblUser";
$where = '';
if (!empty($searchResults)) {
    $ids = array_map('intval', array_column($searchResults, 'id'));
    $where = " WHERE id IN (".implode(',', $ids).")";
} elseif ($search !== '' && strlen($search) >= 3) {
    // Search performed but no users found
    $where = " WHERE 0";
}
$userSql .= $where;

$countSql = "SELECT COUNT(*) AS count FROM $tblUser".$where;
$countRes = Database::query($countSql);
$countRow = Database::fetch_array($countRes);
$totalCount = (int) $countRow['count'];

if ($perPage !== 'all') {
    $totalPages = (int) ceil($totalCount / (int) $perPage);
    $page = min($page, max($totalPages, 1));
    $offset = ((int) $perPage) * ($page - 1);
    $userSql .= " ORDER BY lastname, firstname LIMIT $perPage OFFSET $offset";
} else {
    $totalPages = 1;
    $userSql .= " ORDER BY lastname, firstname";
}
$users = Database::query($userSql);
// Preload teachers for selection list
$teachersRes = Database::query("SELECT id, firstname, lastname FROM $tblUser WHERE status = ".COURSEMANAGER." ORDER BY lastname, firstname");
$teachers = Database::store_result($teachersRes);

Display::display_header(get_lang('UserTracking', 'user_profile'));

echo '<div class="user-profile-section text-center">';
echo Display::page_subheader(get_lang('SearchUser', 'user_profile'));
echo '<div class="mb-3">';
echo '<form method="get" class="search-form">';
echo '<input type="text" name="search" value="'.Security::remove_XSS($search).'" class="form-control mb-2 search-input" placeholder="'.get_lang('SearchUser', 'user_profile').'">';
echo '<input type="hidden" name="per_page" value="'.Security::remove_XSS($perPage).'">';
echo '<button type="submit" class="btn btn-primary">'.get_lang('Search').'</button>';
echo '</form>';
echo '</div>';
if ($search !== '' && strlen($search) >= 3 && empty($searchResults)) {
    echo Display::return_message(get_lang('NoResults'), 'warning');
}
echo '</div>';

echo '<div class="mb-3" style="max-width:80px;">';
echo '<form method="get" id="per-page-form">';
echo '<input type="hidden" name="search" value="'.Security::remove_XSS($search).'">';
echo '<select name="per_page" class="form-control per-page-select">';
foreach ($perPageOptions as $opt) {
    $sel = ($opt == $perPage) ? ' selected' : '';
    $label = ($opt === 'all') ? get_lang('All') : (string) $opt;
    echo '<option value="'.Security::remove_XSS($opt).'"'.$sel.'>'.$label.'</option>';
}
echo '</select>';
echo '</form>';
echo '</div>';
echo '<br>';

echo '<div class="row">';
// Calculate last week's start (Monday) and end (Sunday) in UTC
$start = new DateTime('monday last week', new DateTimeZone('UTC'));
$start->setTime(0, 0, 0);
$end = clone $start;
$end->modify('+6 days')->setTime(23, 59, 59);
$startUtc = api_get_utc_datetime($start->format('Y-m-d H:i:s'));
$endUtc = api_get_utc_datetime($end->format('Y-m-d H:i:s'));

// Calculate current and next week ranges in UTC
$weekStart = new DateTime('monday this week', new DateTimeZone('UTC'));
$weekStart->setTime(0, 0, 0);
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days')->setTime(23, 59, 59);
$nextWeekStart = clone $weekStart;
$nextWeekStart->modify('+7 days');
$nextWeekEnd = clone $weekEnd;
$nextWeekEnd->modify('+7 days');
$weekStartUtc = api_get_utc_datetime($weekStart->format('Y-m-d H:i:s'));
$weekEndUtc = api_get_utc_datetime($weekEnd->format('Y-m-d H:i:s'));
$nextWeekStartUtc = api_get_utc_datetime($nextWeekStart->format('Y-m-d H:i:s'));
$nextWeekEndUtc = api_get_utc_datetime($nextWeekEnd->format('Y-m-d H:i:s'));

while ($user = Database::fetch_array($users)) {
    $userId = (int) $user['id'];
    echo '<div class="col-md-6">';
    echo '<div class="card user-profile" data-user-id="'.$userId.'">';
    echo '<div class="card-title"><strong>'.Security::remove_XSS($user['firstname'].' '.$user['lastname']).'</strong></div>';
    echo '<div class="card-body"><div class="row">';

    echo '<div class="col-sm-8">';
    echo '<ul class="list-group list-group-flush">';
    echo '<li class="list-group-item"><strong>'.get_lang('Email').':</strong> '.Security::remove_XSS($user['email']).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('Phone').':</strong> '.Security::remove_XSS($user['phone']).'</li>';
    $registrationDate = (!empty($user['registration_date']) && $user['registration_date'] !== '0000-00-00 00:00:00')
        ? api_format_date($user['registration_date'], $dateDisplayFormat)
        : '';
    $lastLogin = (!empty($user['last_login']) && $user['last_login'] !== '0000-00-00 00:00:00')
        ? api_format_date($user['last_login'], $dateDisplayFormat)
        : '';

    $tblCourseUser = Database::get_main_table(TABLE_MAIN_COURSE_USER);
    $tblAgenda = Database::get_course_table(TABLE_AGENDA);
    $courseRes = Database::query("SELECT DISTINCT c_id FROM $tblCourseUser WHERE user_id = $userId");
    $courseIds = Database::store_result($courseRes);
    $hasThisWeek = false;
    $hasNextWeek = false;
    if (!empty($courseIds)) {
        $ids = implode(',', array_map('intval', array_column($courseIds, 'c_id')));
        $sqlWeek = "SELECT 1 FROM $tblAgenda WHERE c_id IN ($ids) AND start_date >= '$weekStartUtc' AND start_date <= '$weekEndUtc' LIMIT 1";
        $weekRes = Database::query($sqlWeek);
        $hasThisWeek = Database::num_rows($weekRes) > 0;
        $sqlNext = "SELECT 1 FROM $tblAgenda WHERE c_id IN ($ids) AND start_date >= '$nextWeekStartUtc' AND start_date <= '$nextWeekEndUtc' LIMIT 1";
        $nextRes = Database::query($sqlNext);
        $hasNextWeek = Database::num_rows($nextRes) > 0;
    }
    $thisWeekBox = '<span style="display:inline-block;width:12px;height:12px;background:'.($hasThisWeek ? '#28a745' : '#dc3545').'"></span>';
    $nextWeekBox = '<span style="display:inline-block;width:12px;height:12px;background:'.($hasNextWeek ? '#28a745' : '#dc3545').'"></span>';

    echo '<li class="list-group-item"><strong>'.get_lang('RegistrationDate').':</strong> '.Security::remove_XSS($registrationDate).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('LastLogins').':</strong> '.Security::remove_XSS($lastLogin).'</li>';
    echo '<li class="list-group-item"><strong>'.get_lang('Agenda').':</strong> '.$thisWeekBox.' | '.$nextWeekBox.' <button class="btn btn-warning btn-sm ml-2 agenda-remind-btn">Relancer</button></li>';
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
    $detailsUrl = api_get_path(WEB_CODE_PATH).'mySpace/myStudents.php?student='.$userId;
    $profileUrl = api_get_path(WEB_PLUGIN_PATH).'user_profile/view.php?id='.$userId;
    echo '<div class="time-block">';
    echo '<span><strong>'.Security::remove_XSS($timeLabel).':</strong> '.Security::remove_XSS(gmdate('H:i:s', $timeSpent)).'</span>';
    echo '</div>';
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
                $rawVal = $field['value'];
                $val = '';
                if ($field['field_type'] === 'date' && !empty($rawVal)) {
                    $formatted = api_format_date($rawVal, $dateDisplayFormat);
                    if (empty($field['checked']) && strtotime($rawVal) < time()) {
                        $val = '<span class="text-danger">'.Security::remove_XSS($formatted).' <em class="fa fa-exclamation-triangle"></em></span>';
                    } else {
                        $val = Security::remove_XSS($formatted);
                    }
                } else {
                    $val = Security::remove_XSS($rawVal);
                }
                $checkedAttr = !empty($field['checked']) ? ' checked' : '';
                echo '<tr>';
                echo '<td>'.Security::remove_XSS($field['name']);
                if ($val !== '') {
                    echo ' : '.$val;
                }
                echo '</td>';
                echo '<td class="text-right"><input type="checkbox" disabled'.$checkedAttr.'></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div>';
        }
    }
    // Selection list of teachers
    if (!empty($teachers)) {
        echo '<div class="mt-3">';
        echo '<label>'.get_lang('Teacher').'</label>';
        echo '<select class="form-control teacher-select">';
        // Default empty option so no teacher is pre-selected
        echo '<option value=""></option>';
        foreach ($teachers as $teacher) {
            $fullName = $teacher['firstname'].' '.$teacher['lastname'];
            echo '<option value="'.((int) $teacher['id']).'">'.Security::remove_XSS($fullName).'</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    echo '<div class="text-center mt-3">';
    echo '<a class="btn btn-danger" href="'.Security::remove_XSS($detailsUrl).'">Suivi</a>';
    echo '<a class="btn btn-primary ml-2" href="'.Security::remove_XSS($profileUrl).'">'.get_lang('UserProfile').'</a>';
    echo '<button class="btn btn-success ml-2 warn-btn">Avertir</button>';
    echo '</div>';
    echo '</div>'; // card-body
    echo '</div></div>';
}
echo '</div>';

if ($totalPages > 1) {
    echo '<nav aria-label="User pagination"><ul class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $page ? ' active' : '';
        $url = '?page='.$i.'&per_page='.urlencode((string) $perPage);
        if ($search !== '') {
            $url .= '&search='.urlencode($search);
        }
        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.$url.'">'.$i.'</a></li>';
    }
    echo '</ul></nav>';
}

Display::display_footer();
