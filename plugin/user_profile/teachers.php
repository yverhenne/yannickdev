<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/UserProfilePlugin.php';

$enabled = api_get_configuration_value('plugin_user_profile_enabled');
if (!$enabled) {
    api_not_allowed(true);
}

api_protect_admin_script();
$plugin = UserProfilePlugin::create();

$token = Security::get_token();
global $htmlHeadXtra;

// Messages sans addslashes (on passera par json_encode ensuite)
$successMsg = get_lang('UpdateSuccess', 'user_profile');
$errorMsg   = get_lang('UpdateError', 'user_profile');
$ajaxUrl    = api_get_path(WEB_PLUGIN_PATH).'user_profile/ajax.php';

// Préparer les valeurs encodées en JSON pour éviter toute interpolation hasardeuse
$jsonFlags  = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$tokenJs    = json_encode($token, $jsonFlags);
$successJs  = json_encode($successMsg, $jsonFlags);
$errorJs    = json_encode($errorMsg, $jsonFlags);
$ajaxUrlJs  = json_encode($ajaxUrl, $jsonFlags);

// Injecter le style et le script via $htmlHeadXtra
$htmlHeadXtra[] = <<<JS
<style>
.teacher-item{display:flex;align-items:center;cursor:pointer;}
.teacher-name{flex-grow:1;}
.teacher-check{display:none;margin-left:8px;}
.teacher-item.checked .teacher-check{display:inline;}
.teacher-msg{margin-left:8px;}
</style>
<script>
let userProfileTeacherToken = {$tokenJs};
const teacherSuccessMsg = {$successJs};
const teacherErrorMsg = {$errorJs};
const ajaxUrl = {$ajaxUrlJs};

$(function(){
    $(document).on("click", ".teacher-item", function(){
        const item = $(this);
        const list = item.closest('.teacher-list');
        const userId = list.data('user-id');
        item.toggleClass('checked');
        const teachers = list.find('.teacher-item.checked').map(function(){
            return $(this).data('teacher-id');
        }).get();
        $.post(ajaxUrl, {
            action: 'save_teachers',
            user_id: userId,
            teachers: teachers,
            sec_token: userProfileTeacherToken
        }, function(resp){
            if (resp && resp.token) {
                userProfileTeacherToken = resp.token;
            }
            const ok = resp && resp.status === 'ok';
            const msg = ok ? teacherSuccessMsg : teacherErrorMsg;
            item.find('.teacher-msg')
                .text(msg)
                .removeClass('text-success text-danger')
                .addClass(ok ? 'text-success' : 'text-danger');
            if (!ok) {
                item.toggleClass('checked');
            }
            setTimeout(function(){ item.find('.teacher-msg').text(''); }, 3000);
        }, 'json').fail(function(){
            item.toggleClass('checked');
            item.find('.teacher-msg')
                .text(teacherErrorMsg)
                .removeClass('text-success')
                .addClass('text-danger');
            setTimeout(function(){ item.find('.teacher-msg').text(''); }, 3000);
        });
    });
});
</script>
JS;

$search = trim($_GET['search'] ?? '');
$limit = $_GET['limit'] ?? 10;
$validLimits = ['10', '20', '30', '50', 'all'];
if (!in_array((string) $limit, $validLimits, true)) {
    $limit = 10;
}
$limitSql = '';
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($limit !== 'all') {
    $limit = (int) $limit;
    $offset = ($page - 1) * $limit;
    $limitSql = " LIMIT $limit OFFSET $offset";
}

$tblUser = Database::get_main_table(TABLE_MAIN_USER);
$tblTeachers = Database::get_main_table(UserProfilePlugin::TABLE_TEACHERS);
$urlId = api_get_current_access_url_id();

$from = "$tblUser u";
if (api_is_multiple_url_enabled()) {
    $tblUrl = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
    $from .= " INNER JOIN $tblUrl url ON (u.id = url.user_id AND url.access_url_id = $urlId)";
}

$where = [];
if ($search !== '') {
    $escaped = Database::escape_string($search);
    $where[] = "(u.firstname LIKE '%$escaped%' OR u.lastname LIKE '%$escaped%')";
}
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM $from$whereSql";
$res = Database::query($countSql);
$total = (int) Database::fetch_row($res)[0];

$sql = "SELECT u.id, u.firstname, u.lastname, t.teacher_ids
        FROM $from
        LEFT JOIN $tblTeachers t ON u.id = t.user_id
        $whereSql
        ORDER BY u.lastname, u.firstname$limitSql";
$res = Database::query($sql);
$users = Database::store_result($res);

$teacherOptions = $plugin->getTeacherOptions();

Display::display_header(get_lang('TeacherManagement', 'user_profile'));

// Navigation links between tracking, administrative tracking, and teacher assignment
$current = basename($_SERVER['SCRIPT_NAME']);
$links = [];
if ($current === 'tracking.php') {
    $links[] = 'Suivi pédagogique';
} else {
    $links[] = '<a href="tracking.php">Suivi pédagogique</a>';
}
if ($current === 'tracking_untracked.php') {
    $links[] = 'Suivi administratif';
} else {
    $links[] = '<a href="tracking_untracked.php">Suivi administratif</a>';
}
if ($current === 'teachers.php') {
    $links[] = get_lang('TeacherAssignment', 'user_profile');
} else {
    $links[] = '<a href="teachers.php">'.get_lang('TeacherAssignment', 'user_profile').'</a>';
}
echo '<div class="mb-3">'.implode(' | ', $links).'</div>';

echo '<div class="user-profile-section">';

echo '<form method="get" class="form-inline mb-3">';
    echo '<input type="text" name="search" value="'.Security::remove_XSS($search).'" class="form-control mr-2" placeholder="'.get_lang('SearchUser', 'user_profile').'">';
    echo '<select name="limit" class="form-control mr-2">';
    foreach (['10','20','30','50','all'] as $opt) {
        $selected = ($opt == ($_GET['limit'] ?? '10')) ? ' selected' : '';
        $label = $opt === 'all' ? get_lang('All') : $opt;
        echo '<option value="'.$opt.'"'.$selected.'>'.$label.'</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="btn btn-primary">'.get_lang('Search').'</button>';
echo '</form>';

echo '<table class="table table-striped">';
    echo '<thead><tr><th>'.get_lang('FirstName').'</th><th>'.get_lang('LastName').'</th><th>'.get_lang('Teachers').'</th></tr></thead><tbody>';
    foreach ($users as $user) {
        $selected = $plugin->getUserTeachers((int) $user['id']);
        echo '<tr>';
        echo '<td>'.Security::remove_XSS($user['firstname']).'</td>';
        echo '<td>'.Security::remove_XSS($user['lastname']).'</td>';
        echo '<td>';
        echo '<div class="teacher-list" data-user-id="'.(int) $user['id'].'">';
        foreach ($teacherOptions as $tid => $name) {
            $checked = in_array((int) $tid, $selected, true) ? ' checked' : '';
            echo '<div class="teacher-item'.$checked.'" data-teacher-id="'.(int)$tid.'">';
            echo '<span class="teacher-name">'.Security::remove_XSS($name).'</span>';
            echo '<span class="teacher-check">&#10003;</span>';
            echo '<span class="teacher-msg"></span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    if (empty($users)) {
        echo '<tr><td colspan="3">'.get_lang('NoSearchResults').'</td></tr>';
    }
    echo '</tbody>';
echo '</table>';

if ($limit !== 'all' && $total > $limit) {
    $totalPages = (int) ceil($total / $limit);
    echo '<nav><ul class="pagination">';
    for ($p = 1; $p <= $totalPages; $p++) {
        $active = $p === $page ? ' active' : '';
        $url = api_get_self().'?page='.$p.'&limit='.($_GET['limit'] ?? '10');
        if ($search !== '') {
            $url .= '&search='.urlencode($search);
        }
        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.$url.'">'.$p.'</a></li>';
    }
    echo '</ul></nav>';
}

echo '</div>';

Display::display_footer();

