<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_question\local\bank;

use core\exception\coding_exception;
use core\output\datafilter;
use core_customfield\data_controller;
use core_customfield\field_controller;

/**
 * Base class for question bank filter conditions on a question custom field.
 *
 * A custom field type plugin supports filtering questions by adding a class named
 * `\customfield_<type>\question\filter_condition` which extends this class. {@see create()} finds that class for a field,
 * and one condition is created for each field, so each instance has its own key.
 *
 * Subclasses must implement {@see build_query()}, using {@see get_value_sql()} to get the field's value for each question.
 * The deprecated static methods of {@see condition} cannot be used, as they do not know the field.
 *
 * @package   core_question
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class customfield_condition extends condition {
    /** @var int Counter used to give each value subquery unique placeholder names. */
    private static int $paramcount = 0;

    /** @var string The column of the customfield_data table holding this field's value. */
    protected string $datafield;

    /** @var int|float|string|null The value of the field for questions with no stored value. */
    protected int|float|string|null $defaultvalue;

    /** @var string The component of the field's data. */
    protected string $component;

    /** @var string The area of the field's data. */
    protected string $area;

    /** @var int The item id of the field's data. */
    protected int $itemid;

    /**
     * Create a condition for the given field.
     *
     * @param field_controller $field The custom field to filter on. This must have been loaded through its handler.
     * @param view|null $qbank The question bank view the filter is being rendered for, if any.
     */
    public function __construct(
        /** @var field_controller The custom field to filter on. */
        protected field_controller $field,
        ?view $qbank = null,
    ) {
        $datacontroller = data_controller::create(0, null, $field);
        $this->datafield = $datacontroller->datafield();
        $this->defaultvalue = $datacontroller->get_default_value();
        $this->component = $datacontroller->get('component');
        $this->area = $datacontroller->get('area');
        $this->itemid = $datacontroller->get('itemid');
        parent::__construct($qbank);
    }

    /**
     * Create the filter condition for a field, if its type supports filtering.
     *
     * @param field_controller $field The custom field to filter on. This must have been loaded through its handler.
     * @param view|null $qbank The question bank view the filter is being rendered for, if any.
     * @return self|null The condition, or null if the field type does not support filtering.
     */
    public static function create(field_controller $field, ?view $qbank = null): ?self {
        $classname = '\\customfield_' . $field->get('type') . '\\question\\filter_condition';
        if (!class_exists($classname) || !is_subclass_of($classname, self::class)) {
            return null;
        }
        return new $classname($field, $qbank);
    }

    /**
     * Get the key for this condition, which is unique to the field.
     *
     * @return string
     */
    #[\Override]
    final public function get_key(): string {
        return 'customfield_' . $this->field->get('shortname');
    }

    #[\Override]
    public function find_filter(array $filters): ?array {
        return $filters[$this->get_key()] ?? null;
    }

    /**
     * This cannot be used for custom field conditions, as the field is not known. Use {@see get_key()}.
     *
     * @return string
     * @throws coding_exception Always.
     */
    #[\Override]
    public static function get_condition_key() {
        throw new coding_exception('Use get_key() on a custom field condition instance.');
    }

    /**
     * This cannot be used for custom field conditions, as the field is not known. Use {@see find_filter()}.
     *
     * @param array $filters Array of filters, keyed by condition.
     * @return ?array
     * @throws coding_exception Always.
     */
    #[\Override]
    public static function get_filter_from_list(array $filters): ?array {
        throw new coding_exception('Use find_filter() on a custom field condition instance.');
    }

    /**
     * This cannot be used for custom field conditions, as the field is not known. Use {@see build_query()}.
     *
     * @param array $filter filter properties
     * @return array
     * @throws coding_exception Always.
     */
    #[\Override]
    public static function build_query_from_filter(array $filter): array {
        throw new coding_exception('Use build_query() on a custom field condition instance.');
    }

    #[\Override]
    public function get_title() {
        return $this->field->get_formatted_name(false);
    }

    /**
     * Get an SQL expression for the value of this field for the question with the id q.id.
     *
     * Questions with no stored value get the field's default value, or the fallback if there is no default.
     * Each call uses new placeholder names, so call this again for each place the value is used in the query.
     *
     * @param int|float|string|null $fallback The value to use if the field has no stored value or default.
     * @return array [string $sql, array $params]
     */
    protected function get_value_sql(int|float|string|null $fallback = null): array {
        $param = self::get_unique_param_name();
        $fieldid = (int) $this->field->get('id');
        $itemid = (int) $this->itemid;
        $sql = "(SELECT cfd.{$this->datafield}
                   FROM {customfield_data} cfd
                  WHERE cfd.instanceid = q.id
                        AND cfd.fieldid = {$fieldid}
                        AND cfd.component = :{$param}c
                        AND cfd.area = :{$param}a
                        AND cfd.itemid = {$itemid})";
        $params = [
            "{$param}c" => $this->component,
            "{$param}a" => $this->area,
        ];
        $default = $this->defaultvalue ?? $fallback;
        if (!is_null($default)) {
            $sql = "COALESCE({$sql}, :{$param}d)";
            $params["{$param}d"] = $default;
        }
        return [$sql, $params];
    }

    /**
     * Build a condition matching the field's value against each keyword, for use with the keyword filter type.
     *
     * Values match if they contain the keyword, ignoring case. The jointype decides whether the value must match any, all or
     * none of the keywords. Questions with no value and no default are treated as having an empty value, so they match
     * "none" of the keywords.
     *
     * @param array $filter filter properties
     * @return array [string $where, array $params]
     */
    protected function build_keyword_query(array $filter): array {
        global $DB;

        $jointype = (int) ($filter['jointype'] ?? self::JOINTYPE_DEFAULT);
        $notlike = $jointype === datafilter::JOINTYPE_NONE;
        $conditions = [];
        $params = [];
        foreach ($filter['values'] ?? [] as $value) {
            [$valuesql, $valueparams] = $this->get_value_sql('');
            $param = self::get_unique_param_name();
            $conditions[] = $DB->sql_like($valuesql, ":{$param}", casesensitive: false, notlike: $notlike);
            $params = array_merge($params, $valueparams, [$param => '%' . $DB->sql_like_escape($value) . '%']);
        }
        $delimiter = $jointype === datafilter::JOINTYPE_ANY ? ' OR ' : ' AND ';
        return [implode($delimiter, $conditions), $params];
    }

    /**
     * Get a placeholder name which has not been used by any custom field condition yet.
     *
     * @return string
     */
    protected static function get_unique_param_name(): string {
        self::$paramcount++;
        return 'qbcf' . self::$paramcount;
    }
}
