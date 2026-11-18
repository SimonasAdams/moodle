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

namespace customfield_date\question;

use core\exception\moodle_exception;
use core_question\local\bank\customfield_condition;
use core_question\local\bank\datetime_condition_trait;

/**
 * Question bank filter condition for a date custom field, searching for dates before, after or between the selected dates.
 *
 * @package   customfield_date
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_condition extends customfield_condition {
    use datetime_condition_trait;

    /**
     * Build an SQL WHERE condition to filter questions on the field's date.
     *
     * Questions without a date never match.
     *
     * @param array $filter ['values' => [$after, $before], 'filteroptions' => ['mode' => $mode]]
     * @return array [string $where, array $params]
     * @throws moodle_exception If an invalid mode or range is provided.
     */
    #[\Override]
    public function build_query(array $filter): array {
        $range = self::parse_datetime_filter($filter);
        if (is_null($range)) {
            return ['', []];
        }
        [$after, $before] = $range;

        // An empty date is stored as 0.
        [$valuesql, $params] = $this->get_value_sql();
        $conditions = ["{$valuesql} <> 0"];
        foreach (['>' => $after, '<' => $before] as $operator => $timestamp) {
            if (is_null($timestamp)) {
                continue;
            }
            [$valuesql, $valueparams] = $this->get_value_sql();
            $param = self::get_unique_param_name();
            $conditions[] = "{$valuesql} {$operator} :{$param}";
            $params = array_merge($params, $valueparams, [$param => $timestamp]);
        }

        return [implode(' AND ', $conditions), $params];
    }
}
