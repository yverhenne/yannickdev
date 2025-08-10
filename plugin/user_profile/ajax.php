<?php
require_once __DIR__.'/config.php';
if (!api_get_configuration_value('plugin_user_profile_enabled')) {
    api_not_allowed(true);
}
require_once __DIR__.'/UserProfilePlugin.php';
require_once api_get_path(LIBRARY_PATH).'message.lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check = Security::check_token('post');
    $status = 'error';
    if ($check && isset($_POST['action'])) {
        if ($_POST['action'] === 'toggle') {
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
        } elseif ($_POST['action'] === 'warn') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $teacherId = (int) ($_POST['teacher_id'] ?? 0);
            if ($userId && $teacherId) {
                $tblUser = Database::get_main_table(TABLE_MAIN_USER);
                $userInfo = Database::fetch_array(
                    Database::query("SELECT firstname, lastname FROM $tblUser WHERE id = $userId"),
                    'ASSOC'
                );
                $userName = $userInfo ? $userInfo['firstname'].' '.$userInfo['lastname'] : '';

                $tblField = Database::get_main_table(UserProfilePlugin::TABLE_FIELD);
                $tblValue = Database::get_main_table(UserProfilePlugin::TABLE_VALUE);
                $tblCat = Database::get_main_table(UserProfilePlugin::TABLE_CATEGORY);
                $urlId = api_get_current_access_url_id();
                $sql = "SELECT f.name, f.field_type, v.value
                        FROM $tblField f
                        LEFT JOIN $tblValue v ON (f.id = v.field_id AND v.user_id = $userId)
                        LEFT JOIN $tblCat c ON (f.category_id = c.id)
                        WHERE f.access_url_id = $urlId AND c.access_url_id = $urlId
                          AND f.include_tracking = 1 AND COALESCE(v.checked,0) = 0";
                $res = Database::query($sql);
                $lines = [];
                while ($row = Database::fetch_array($res, 'ASSOC')) {
                    $value = $row['value'];
                    if ($row['field_type'] === 'date' && !empty($value)) {
                        $value = api_format_date($value, DATE_FORMAT_LONG);
                    }
                    $lines[] = '- '.$row['name'].' : '.$value;
                }
                $intro = 'Bonjour, les éléments suivants méritent votre attention :';
                $outro = 'Cordialement';

                $userUrl = api_get_path(WEB_CODE_PATH).'mySpace/myStudents.php?student='.$userId;
                $userLine = '<a href="'.$userUrl.'">'.$userName.'</a>';

                $body = implode('<br>', $lines);
                $content = $intro.'<br>'.$userLine.'<br>'.$body.'<br>'.$outro;

                $subject = 'Avertissement pour '.$userName;
                MessageManager::send_message_simple($teacherId, $subject, $content);
            }
            $status = 'ok';
        }
    }
    Security::clear_token();
    header('Content-Type: application/json');
    echo json_encode(['token' => Security::get_token(), 'status' => $status]);
    exit;
}
?>
