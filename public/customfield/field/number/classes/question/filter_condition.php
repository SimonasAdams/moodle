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

namespace customfield_number\question;

use core\exception\moodle_exception;
use core_question\local\bank\customfield_condition;

/**
 * Question bank filter condition for a number custom field, comparing the field's value with one or two numbers.
 *
 * @package   customfield_number
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_condition extends customfield_condition {
    /** @var string Search for values equal to the specified number. */
    const MODE_EQUAL = 'equal';

    /** @var string Search for values less than the specified number. */
    const MODE_LESSTHAN = 'lessthan';

    /** @var string Search for values greater than the specified number. */
    const MODE_GREATERTHAN = 'greaterthan';

    /** @var string Search for values between the specified numbers, inclusive. */
    const MODE_BETWEEN = 'between';

    #[\Override]
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/number';
    }

    /**
     * Set a single valid jointype, so we don't display the jointype selector.
     *
     * We have a separate filter option to control how this condition is applied, Any/All/None doesn't apply here.
     *
     * @return array
     */
    #[\Override]
    public function get_join_list(): array {
        return [
            self::JOINTYPE_DEFAULT,
        ];
    }

    /**
     * Build an SQL WHERE condition comparing the field's value with the filter values.
     *
     * $filter['values'][0] contains the number to compare with, or the lower limit when searching between two numbers.
     * $filter['values'][1] contains the upper limit when searching between two numbers. How the numbers are compared is
     * determined by $filter['filteroptions']['mode']. Questions with no value and no default never match.
     *
     * @param array $filter ['values' => [$value1, $value2], 'filteroptions' => ['mode' => $mode]]
     * @return array [string $where, array $params]
     * @throws moodle_exception If an invalid mode or value is provided.
     */
    #[\Override]
    public function build_query(array $filter): array {
        if (!isset($filter['filteroptions']['mode']) || empty($filter['values'])) {
            return ['', []];
        }
        $mode = $filter['filteroptions']['mode'];
        $operators = [
            self::MODE_EQUAL => '=',
            self::MODE_LESSTHAN => '<',
            self::MODE_GREATERTHAN => '>',
        ];
        if ($mode !== self::MODE_BETWEEN && !array_key_exists($mode, $operators)) {
            throw new moodle_exception('invalidmode', 'error', a: $mode);
        }
        $values = array_slice(array_values($filter['values']), 0, $mode === self::MODE_BETWEEN ? 2 : 1);
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                throw new moodle_exception('invalidnum', 'error');
            }
        }
        $values = array_map('floatval', $values);

        [$valuesql, $params] = $this->get_value_sql();
        $param = self::get_unique_param_name();
        if ($mode === self::MODE_BETWEEN) {
            if (count($values) < 2) {
                throw new moodle_exception('invalidnum', 'error');
            }
            if ($values[0] > $values[1]) {
                throw new moodle_exception('invalidnumberbetween', 'error', a: (object) ['min' => $values[0], 'max' => $values[1]]);
            }
            $params["{$param}min"] = $values[0];
            $params["{$param}max"] = $values[1];
            return ["{$valuesql} BETWEEN :{$param}min AND :{$param}max", $params];
        }
        $params[$param] = $values[0];
        return ["{$valuesql} {$operators[$mode]} :{$param}", $params];
    }
}
