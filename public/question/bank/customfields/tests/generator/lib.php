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

use core_customfield\data_controller;
use qbank_customfields\customfield\question_handler;

/**
 * Data generator for qbank_customfields.
 *
 * @package   qbank_customfields
 * @category  test
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbank_customfields_generator extends component_generator_base {
    /**
     * Set the value of a question custom field for a question.
     *
     * String values are converted with the field's parse_value() method, so a dropdown menu option can be given by its label,
     * and a date as any string strtotime() understands. Other values are stored as given.
     *
     * @param array|stdClass $record With the keys questionid, field (the field's shortname) and value.
     * @return data_controller
     */
    public function create_question_custom_field_value(array|stdClass $record): data_controller {
        $record = (array) $record;
        $field = null;
        foreach (question_handler::create()->get_fields() as $candidate) {
            if ($candidate->get('shortname') === $record['field']) {
                $field = $candidate;
                break;
            }
        }
        if (!$field) {
            throw new coding_exception('There is no question custom field with the shortname "' . $record['field'] . '".');
        }

        $value = $record['value'];
        if (is_string($value)) {
            $value = $field->parse_value($value);
        }
        if ($field->get('type') === 'textarea') {
            $value = ['text' => $value, 'format' => FORMAT_HTML];
        }

        /** @var core_customfield_generator $customfieldgenerator */
        $customfieldgenerator = $this->datagenerator->get_plugin_generator('core_customfield');
        return $customfieldgenerator->add_instance_data($field, $record['questionid'], $value);
    }
}
