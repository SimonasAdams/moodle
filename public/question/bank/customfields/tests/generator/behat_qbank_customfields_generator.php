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

/**
 * Behat data generator for qbank_customfields.
 *
 * @package   qbank_customfields
 * @category  test
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qbank_customfields_generator extends behat_generator_base {
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'question custom field values' => [
                'singular' => 'question custom field value',
                'datagenerator' => 'question_custom_field_value',
                'required' => ['question', 'field', 'value'],
                'switchids' => ['question' => 'questionid'],
            ],
        ];
    }

    /**
     * Look up the id of a question from its name.
     *
     * @param string $questionname the question name, for example 'Question 1'.
     * @return int corresponding id.
     */
    protected function get_question_id(string $questionname): int {
        global $DB;

        if (!$id = $DB->get_field('question', 'id', ['name' => $questionname])) {
            throw new Exception('There is no question with name "' . $questionname . '".');
        }
        return $id;
    }
}
