<?php

/**
 * Class mailerMigrates
 *
 * Update db structures/data (in webasyst terminology so called meta-updates)
 */
class mailerMigrates
{
    /**
     * @param string|array[] string $table
     * @param string $app_id
     */
    public function createTable($table, $app_id = 'mailer')
    {
        $tables = array_map('strval', (array)$table);
        if (empty($tables)) {
            return;
        }

        $db_path = wa()->getAppPath('lib/config/db.php', $app_id);
        $db = include($db_path);

        $db_partial = array();
        foreach ($tables as $table) {
            if (isset($db[$table])) {
                $db_partial[$table] = $db[$table];
            }
        }

        if (empty($db_partial)) {
            return;
        }

        $m = new waModel();
        $m->createSchema($db_partial);
    }

    /**
     * @param $table
     * @param $column_name
     * @param $column_definition
     * @param string|null $after_column
     * @param Closure|null $after_alter
     */
    public function addColumn($table, $column_name, $column_definition, $after_column = null, $after_alter = null)
    {
        $disable_exception_log = waConfig::get('disable_exception_log');
        waConfig::set('disable_exception_log', true);

        $m = new waModel();

        try {
            $m->query("SELECT `{$column_name}` FROM `{$table}` WHERE 0");
        } catch (waDbException $e) {
            waConfig::set('disable_exception_log', false);

            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column_name}` {$column_definition}";
            if ($after_column) {
                $sql .= " AFTER {$after_column}";
            }
            $m->exec($sql);

            if (is_callable($after_alter)) {
                $after_alter();
            }
        }

        waConfig::set('disable_exception_log', $disable_exception_log);
    }

    public function renameColumn($table, $old_column_name, $new_column_name, $column_definition)
    {
        $disable_exception_log = waConfig::get('disable_exception_log');
        waConfig::set('disable_exception_log', true);

        $m = new waModel();

        try {
            $m->query("SELECT `{$new_column_name}` FROM `{$table}` WHERE 0");
        } catch (waDbException $e) {
            waConfig::set('disable_exception_log', false);
            $sql = "ALTER TABLE `{$table}` CHANGE `{$old_column_name}` `{$new_column_name}` {$column_definition}";
            $m->exec($sql);
        }

        waConfig::set('disable_exception_log', $disable_exception_log);
    }

    public function changeColumn($table, $column_name, $column_definition)
    {
        $m = new waModel();
        $sql = "ALTER TABLE `{$table}` CHANGE `{$column_name}` `{$column_name}` {$column_definition}";
        $m->query($sql);
    }

    public function dropColumn($table, $column_name)
    {
        $disable_exception_log = waConfig::get('disable_exception_log');
        waConfig::set('disable_exception_log', true);

        $m = new waModel();

        try {
            $m->query("SELECT `{$column_name}` FROM `{$table}` WHERE 0");
            $m->exec("ALTER TABLE `{$table}` DROP `{$column_name}`");
        } catch (waDbException $e) {
            waConfig::set('disable_exception_log', false);
        }

        waConfig::set('disable_exception_log', $disable_exception_log);
    }

    /**
     * Add one or few enum items to existed enum field
     * @param string $table
     * @param string $column_name
     * @param string|string[] $enum_item
     * @throws waDbException
     * @throws waException
     */
    public function addEnumItems($table, $column_name, $enum_item)
    {
        $new_enum_items = waUtils::toStrArray($enum_item);
        if (!$new_enum_items) {
            return;
        }

        $m = new waModel();

        $columns = $m->query("SHOW COLUMNS FROM `{$table}`")->fetchAll('Field');

        $enum_value_str = $columns[$column_name]['Type'];
        $enum_value_str = substr($enum_value_str, 5, -1); // get ENUM items
        $enum_items = explode(',', $enum_value_str);  // convert to array

        foreach ($new_enum_items as $item) {
            $item = trim($item, "'");
            $item = "'{$item}'";
            $enum_items[] = $item;
        }

        sort($enum_items);

        $enum_items = array_unique($enum_items);

        $enum_value_new_str = join(',', $enum_items);

        // not changed
        if ($enum_value_str == $enum_value_new_str) {
            return;
        }

        $is_not_null = strtoupper($columns[$column_name]['Null']) === 'NO';
        $default = trim($columns[$column_name]['Default']);

        $definition = "ENUM({$enum_value_new_str})" .
            ($is_not_null ? ' NOT NULL' : ' NULL') .
            (strlen($default)  > 0 ? " DEFAULT '{$default}'" : '');

        $this->changeColumn($table, $column_name, $definition);
    }
}
