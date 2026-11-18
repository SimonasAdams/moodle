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

namespace customfield_select\question;

use core\output\datafilter;
use core_question\local\bank\customfield_condition;

/**
 * Question bank filter condition for a dropdown menu custom field, selecting questions with any or none of the chosen options.
 *
 * @package   customfield_select
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_condition extends customfield_condition {
    #[\Override]
    public function allow_custom() {
        return false;
    }

    /**
     * Get the list of available joins for the filter.
     *
     * A question can only have one option selected, so matching "all" of several options does not make sense.
     *
     * @return array
     */
    #[\Override]
    public function get_join_list(): array {
        return [
            datafilter::JOINTYPE_NONE,
            datafilter::JOINTYPE_ANY,
        ];
    }

    /**
     * List the field's options, identified by their position in the list as they are stored.
     *
     * @return array
     */
    #[\Override]
    public function get_initial_values(): array {
        /** @var \customfield_select\field_controller $field */
        $field = $this->field;
        $values = [];
        foreach ($field->get_options() as $index => $option) {
            // The first option is the empty value.
            if ($index === 0) {
                continue;
            }
            $values[] = [
                'value' => $index,
                'title' => $option,
            ];
        }
        return $values;
    }

    /**
     * Build an SQL WHERE condition selecting questions with any, or none, of the selected options.
     *
     * @param array $filter ['values' => [option index, ...], 'jointype' => jointype]
     * @return array [string $where, array $params]
     */
    #[\Override]
    public function build_query(array $filter): array {
        global $DB;

        $selected = array_filter(array_map('intval', $filter['values'] ?? []));
        if (empty($selected)) {
            return ['', []];
        }
        $equal = (int) ($filter['jointype'] ?? self::JOINTYPE_DEFAULT) !== datafilter::JOINTYPE_NONE;
        [$valuesql, $params] = $this->get_value_sql(0);
        [$insql, $inparams] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED, 'qbcfsel', $equal);
        return ["{$valuesql} {$insql}", array_merge($params, $inparams)];
    }
}
