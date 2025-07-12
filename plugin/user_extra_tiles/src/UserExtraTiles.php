<?php
/* For licensing terms, see /license.txt */

class UserExtraTiles extends Plugin
{
    public const FIELD_PREFIX = 'uetile_';
    public const TABLE_FIELD = 'plugin_user_extra_tiles_field';
    public const TABLE_VALUE = 'plugin_user_extra_tiles_value';

    protected function __construct()
    {
        parent::__construct('1.0', 'Auto generated');
    }

    public static function create()
    {
        static $result = null;
        return $result ?: $result = new self();
    }

    public function get_name()
    {
        return 'user_extra_tiles';
    }

    public function install()
    {
        $sql = "CREATE TABLE IF NOT EXISTS ".self::TABLE_FIELD." (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            variable VARCHAR(100) NOT NULL,
            display_text VARCHAR(255) NOT NULL,
            field_order INT NOT NULL DEFAULT 0
        )";
        Database::query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS ".self::TABLE_VALUE." (
            field_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            value TEXT,
            PRIMARY KEY (field_id, user_id)
        )";
        Database::query($sql);
    }

    public function uninstall()
    {
        Database::query('DROP TABLE IF EXISTS '.self::TABLE_VALUE);
        Database::query('DROP TABLE IF EXISTS '.self::TABLE_FIELD);
    }

    public function getFields()
    {
        return Database::select(
            '*',
            self::TABLE_FIELD,
            ['order' => 'field_order ASC'],
            'all'
        );
    }

    public function createField($variable, $displayText, $order)
    {
        $params = [
            'variable' => self::FIELD_PREFIX.$variable,
            'display_text' => $displayText,
            'field_order' => (int) $order,
        ];

        return Database::insert(self::TABLE_FIELD, $params);
    }

    public function updateOrder($id, $order)
    {
        return Database::update(
            self::TABLE_FIELD,
            ['field_order' => (int) $order],
            ['id = ?' => (int) $id]
        );
    }

    public function deleteField($id)
    {
        Database::delete(self::TABLE_VALUE, ['field_id = ?' => (int) $id]);
        Database::delete(self::TABLE_FIELD, ['id = ?' => (int) $id]);
    }

    public function getFieldValues($userId)
    {
        $values = [];
        foreach ($this->getFields() as $field) {
            $value = Database::select(
                'value',
                self::TABLE_VALUE,
                ['where' => ['field_id = ? AND user_id = ?' => [(int) $field['id'], (int) $userId]]],
                'first'
            );
            $values[] = [
                'name' => $field['display_text'],
                'value' => $value['value'] ?? '',
            ];
        }

        return $values;
    }

    public function setFieldValue($userId, $fieldId, $value)
    {
        $exists = Database::select(
            'field_id',
            self::TABLE_VALUE,
            ['where' => ['field_id = ? AND user_id = ?' => [(int) $fieldId, (int) $userId]], 'limit' => 1],
            'first'
        );

        if ($exists) {
            Database::update(
                self::TABLE_VALUE,
                ['value' => $value],
                ['field_id = ? AND user_id = ?' => [(int) $fieldId, (int) $userId]]
            );
        } else {
            Database::insert(
                self::TABLE_VALUE,
                [
                    'field_id' => (int) $fieldId,
                    'user_id' => (int) $userId,
                    'value' => $value,
                ]
            );
        }
    }
}
