<?php
/* For license terms, see /license.txt */
require_once __DIR__.'/src/HookUserProfile.php';

class UserProfilePlugin extends Plugin implements HookPluginInterface
{
    public const TABLE_FIELD = 'plugin_user_profile_field';
    public const TABLE_VALUE = 'plugin_user_profile_value';
    public const TABLE_CATEGORY = 'plugin_user_profile_category';
    public const TABLE_TEACHERS = 'plugin_user_profile_teachers';

    public function get_name(): string
    {
        return 'user_profile';
    }

    public static function getCategoryLabel(array $category): string
    {
        $name = $category['name'];
        $label = self::create()->get_lang($name);

        // get_lang() returns the key in brackets when missing; fallback to raw
        if (preg_match('/^\[[=]?' . preg_quote($name, '/') . '[=]?\]$/', $label)) {
            return $name;
        }

        return $label;
    }

    protected function __construct()
    {
        parent::__construct('1.0', 'Yannick VERHENNE');
    }

    public static function create(): UserProfilePlugin
    {
        static $instance = null;

        return $instance ?: $instance = new self();
    }

    public function install()
    {
        $tblField = Database::get_main_table(self::TABLE_FIELD);
        $tblValue = Database::get_main_table(self::TABLE_VALUE);
        $tblCat = Database::get_main_table(self::TABLE_CATEGORY);
        $tblTeachers = Database::get_main_table(self::TABLE_TEACHERS);
        $urlId = api_get_current_access_url_id();

        $sql = "CREATE TABLE IF NOT EXISTS $tblCat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            access_url_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            cat_order INT NOT NULL DEFAULT 0,
            INDEX (access_url_id)
        )";
        Database::query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS $tblField (
            id INT AUTO_INCREMENT PRIMARY KEY,
            access_url_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            field_type VARCHAR(10) NOT NULL,
            category_id INT NOT NULL,
            field_order INT NOT NULL DEFAULT 0,
            include_tracking TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (category_id) REFERENCES $tblCat(id) ON DELETE CASCADE,
            INDEX (access_url_id)
        )";
        Database::query($sql);
        // Ensure include_tracking exists for legacy installations
        $res = Database::query("SHOW COLUMNS FROM $tblField LIKE 'include_tracking'");
        if (0 === Database::num_rows($res)) {
            Database::query("ALTER TABLE $tblField ADD include_tracking TINYINT(1) NOT NULL DEFAULT 0");
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tblValue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            field_id INT NOT NULL,
            value TEXT,
            checked TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_user_field(user_id, field_id)
        )";
        Database::query($sql);
        // Ensure checked column exists for legacy installations
        $res = Database::query("SHOW COLUMNS FROM $tblValue LIKE 'checked'");
        if (0 === Database::num_rows($res)) {
            Database::query("ALTER TABLE $tblValue ADD checked TINYINT(1) NOT NULL DEFAULT 0");
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tblTeachers (
            user_id INT NOT NULL PRIMARY KEY,
            teacher_ids TEXT
        )";
        Database::query($sql);

        $this->installHook();
    }

    public function uninstall()
    {
        $tables = [self::TABLE_FIELD, self::TABLE_VALUE, self::TABLE_CATEGORY, self::TABLE_TEACHERS];
        foreach ($tables as $table) {
            $tableName = Database::get_main_table($table);
            $sql = "DROP TABLE IF EXISTS $tableName";
            Database::query($sql);
        }

        $this->uninstallHook();
    }

    public function installHook()
    {
        $observer = HookUserProfile::create();
        HookCreateUser::create()->attach($observer);
        HookUpdateUser::create()->attach($observer);
        HookAdminBlock::create()->attach($observer);

        return 1;
    }

    public function uninstallHook()
    {
        $observer = HookUserProfile::create();
        HookCreateUser::create()->detach($observer);
        HookUpdateUser::create()->detach($observer);
        HookAdminBlock::create()->detach($observer);

        return 1;
    }

    public function getAdminUrl()
    {
        return api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/admin.php';
    }

    public function getViewUrl(int $userId, bool $fromSearch = false): string
    {
        $url = api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/view.php?id='.$userId;
        if ($fromSearch) {
            $url .= '&from_search=1';
        }
        return $url;
    }

    public function getTrackingUrl(): string
    {
        return api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/tracking.php';
    }

    public function getTeacherManagementUrl(): string
    {
        return api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/teachers.php';
    }

    public function getCategories(): array
    {
        $table = Database::get_main_table(self::TABLE_CATEGORY);
        $urlId = api_get_current_access_url_id();
        $res = Database::query("SELECT * FROM $table WHERE access_url_id = $urlId ORDER BY cat_order, id");
        return Database::store_result($res);
    }

    public function getCategoryOptions(): array
    {
        $options = [];
        foreach ($this->getCategories() as $cat) {
            $options[$cat['id']] = self::getCategoryLabel($cat);
        }
        return $options;
    }

    public function getFields(): array
    {
        $table = Database::get_main_table(self::TABLE_FIELD);
        $urlId = api_get_current_access_url_id();
        $res = Database::query("SELECT * FROM $table WHERE access_url_id = $urlId ORDER BY field_order, id");
        return Database::store_result($res);
    }

    public function addFieldsToForm(FormValidator $form, ?int $userId = null)
    {
        $values = [];
        if ($userId) {
            $values = $this->getUserValues($userId);
        }

        $fields = $this->getFields();
        $byCat = [];
        foreach ($fields as $field) {
            $byCat[$field['category_id']][] = $field;
        }

        foreach ($this->getCategories() as $cat) {
            $form->addHtml('<h5 style="text-align: center;"><strong>'.Security::remove_XSS($cat['name']).'</strong></h5></br>');
            if (empty($byCat[$cat['id']])) {
                continue;
            }
            foreach ($byCat[$cat['id']] as $field) {
                $name = 'profile_'.$field['id'];
                if ($field['field_type'] === 'date') {
                    $form->addDatePicker($name, $field['name']);
                } else {
                    $form->addText($name, $field['name'], false);
                }
                if (isset($values[$field['id']])) {
                    $form->setDefaults([
                        $name => $values[$field['id']]['value'],
                    ]);
                }
            }
        }
    }

    public function saveUserValues(int $userId, array $formValues)
    {
        $table = Database::get_main_table(self::TABLE_VALUE);
        foreach ($this->getFields() as $field) {
            $key = 'profile_'.$field['id'];
            if (!array_key_exists($key, $formValues)) {
                continue;
            }
            $value = trim((string) $formValues[$key]);
            $where = ['user_id = ? AND field_id = ?' => [$userId, $field['id']]];
            $existing = Database::select('id', $table, ['where' => $where], 'first');
            if ($existing) {
                Database::update($table, ['value' => $value], $where);
            } elseif ($value !== '') {
                Database::insert($table, [
                    'user_id' => $userId,
                    'field_id' => $field['id'],
                    'value' => $value,
                    'checked' => 0,
                ]);
            }
        }
    }

    public function getUserValues(int $userId): array
    {
        $table = Database::get_main_table(self::TABLE_VALUE);
        $rows = Database::select('*', $table, [
            'where' => ['user_id = ?' => $userId],
        ]);
        $values = [];
        foreach ($rows as $row) {
            $values[$row['field_id']] = [
                'value' => $row['value'],
                'checked' => (int) $row['checked'],
            ];
        }

        return $values;
    }

    public function getTeacherOptions(): array
    {
        $tblUser = Database::get_main_table(TABLE_MAIN_USER);
        $res = Database::query("SELECT id, firstname, lastname FROM $tblUser WHERE status = ".COURSEMANAGER." ORDER BY lastname, firstname");
        $options = [];
        while ($row = Database::fetch_array($res)) {
            $options[$row['id']] = $row['firstname'].' '.$row['lastname'];
        }
        return $options;
    }

    public function saveUserTeachers(int $userId, array $teacherIds): void
    {
        $table = Database::get_main_table(self::TABLE_TEACHERS);
        $teacherIds = array_unique(array_filter(array_map('intval', $teacherIds)));
        $data = ['teacher_ids' => implode(',', $teacherIds)];
        $exists = Database::select('user_id', $table, ['where' => ['user_id = ?' => $userId]], 'first');
        if ($exists) {
            Database::update($table, $data, ['user_id = ?' => $userId]);
        } else {
            $data['user_id'] = $userId;
            Database::insert($table, $data);
        }
    }

    public function getUserTeachers(int $userId): array
    {
        $table = Database::get_main_table(self::TABLE_TEACHERS);
        $row = Database::select('teacher_ids', $table, ['where' => ['user_id = ?' => $userId]], 'first');
        if (!$row || empty($row['teacher_ids'])) {
            return [];
        }
        return array_filter(array_map('intval', explode(',', $row['teacher_ids'])));
    }

    public function getTeacherNamesForUser(int $userId): string
    {
        $ids = $this->getUserTeachers($userId);
        if (empty($ids)) {
            return '';
        }
        $tblUser = Database::get_main_table(TABLE_MAIN_USER);
        $idList = implode(',', array_map('intval', $ids));
        $res = Database::query("SELECT firstname, lastname FROM $tblUser WHERE id IN ($idList) ORDER BY lastname, firstname");
        $names = [];
        while ($row = Database::fetch_array($res)) {
            $names[] = $row['firstname'].' '.$row['lastname'];
        }
        return implode(', ', $names);
    }
}
?>