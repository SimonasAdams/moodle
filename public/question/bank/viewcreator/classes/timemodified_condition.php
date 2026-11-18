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

namespace qbank_viewcreator;

use core\exception\moodle_exception;
use core\plugininfo\filter;
use core_question\local\bank\condition;
use core_question\local\bank\datetime_condition_trait;

/**
 * Filter condition for date/time modified
 *
 * @package   qbank_viewcreator
 * @copyright 2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timemodified_condition extends condition {
    use datetime_condition_trait;

    #[\Override]
    public function get_title() {
        return get_string('timemodified', 'qbank_viewcreator');
    }

    #[\Override]
    public static function get_condition_key() {
        return 'timemodified';
    }

    /**
     * Build an SQL WHERE condition to filter questions based on q.timemodified.
     *
     * $filter['values'][0] contains the datetime to search after, $filter['values'][1] contains the datetime
     * to search before. Whether to use these dates to search after, before, or between these dates is determined
     * by the value of $filter['fileroptions']['mode'].
     *
     * The datetime values are in the format YYYY-MM-DDTHH:mm, as provided by the datetime-local input type.
     *
     * @param array $filter ['values' => [$before, $after], 'filteroptions' => ['mode' => $mode]]
     * @return array
     * @throws moodle_exception If an invalid mode or range is provided.
     */
    public static function build_query_from_filter(array $filter): array {
        $range = self::parse_datetime_filter($filter);
        if (is_null($range)) {
            return ['', []];
        }
        [$after, $before] = $range;
        $conditions = [];
        $params = [];
        if (!is_null($after)) {
            $conditions[] = 'q.timemodified > :timemodifiedafter';
            $params['timemodifiedafter'] = $after;
        }
        if (!is_null($before)) {
            $conditions[] = 'q.timemodified < :timemodifiedbefore';
            $params['timemodifiedbefore'] = $before;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
