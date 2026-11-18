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

namespace customfield_checkbox\question;

use core_question\local\bank\customfield_condition;

/**
 * Question bank filter condition for a checkbox custom field, selecting questions where the box is checked or not.
 *
 * @package   customfield_checkbox
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_condition extends customfield_condition {
    #[\Override]
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/binary';
    }

    /**
     * Set a single valid jointype, so we don't display the jointype selector.
     *
     * @return array
     */
    #[\Override]
    public function get_join_list(): array {
        return [
            self::JOINTYPE_DEFAULT,
        ];
    }

    #[\Override]
    public function allow_multiple() {
        return false;
    }

    /**
     * Build an SQL WHERE condition selecting questions where the box is checked (value 1) or not checked (value 0).
     *
     * @param array $filter ['values' => [0 or 1]]
     * @return array [string $where, array $params]
     */
    #[\Override]
    public function build_query(array $filter): array {
        if (!isset($filter['values'][0])) {
            return ['', []];
        }
        [$valuesql, $params] = $this->get_value_sql(0);
        $param = self::get_unique_param_name();
        $params[$param] = empty($filter['values'][0]) ? 0 : 1;
        return ["{$valuesql} = :{$param}", $params];
    }
}
