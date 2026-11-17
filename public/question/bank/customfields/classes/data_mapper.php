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

namespace qbank_customfields;

use core_customfield\api;
use qbank_customfields\customfield\question_handler;

/**
 * Class to handle import and export of customfield data
 *
 * Each custom field is exported as a leaf node named after the field's shortname, holding its value, format and any
 * embedded files. Values are exported with data_controller::prepare_export_value(), so a select field's value is its
 * option label rather than the option's position.
 *
 * On import, values are matched to this site's custom fields by shortname. A value is skipped, with a warning shown
 * to the user, if there is no field with that shortname, the user cannot edit it, or the value is not valid for it.
 *
 * @package   qbank_customfields
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_mapper extends \core_question\local\bank\data_mapper_base {
    #[\Override]
    public function get_question_data(array $questionids): array {
        $questiondata = parent::get_question_data($questionids);
        $customfieldhandler = question_handler::create();
        $qdatacontrollers = $customfieldhandler->get_instances_data($questionids, true);
        foreach ($qdatacontrollers as $qid => $datacontrollers) {
            foreach ($datacontrollers as $datacontroller) {
                $questiondata[$qid][] = [
                    'name' => $datacontroller->get_field()->get('shortname'),
                    'text' => $datacontroller->prepare_export_value(),
                    'format' => (int) $datacontroller->get('valueformat'),
                    'files' => $datacontroller->get_files(),
                ];
            }
        }
        return $questiondata;
    }

    #[\Override]
    public function save_question_data(int $questionid, array $data): void {
        $customfieldhandler = question_handler::create();
        $editablefields = $customfieldhandler->get_editable_fields($questionid);
        $datacontrollers = [];
        foreach (api::get_instance_fields_data($editablefields, $questionid) as $datacontroller) {
            $datacontrollers[$datacontroller->get_field()->get('shortname')] = $datacontroller;
        }

        $instance = (object) ['id' => $questionid];
        foreach ($data as $item) {
            $datacontroller = $datacontrollers[$item['name']] ?? null;
            if ($datacontroller === null || isset($item['children'])) {
                \core\notification::warning(get_string('importfieldskipped', 'qbank_customfields', s($item['name'])));
                continue;
            }
            $value = $datacontroller->prepare_import_value(
                $item['text'],
                $item['format'] ?? FORMAT_PLAIN,
                $item['itemid'] ?? null,
            );
            if ($value === null) {
                \core\notification::warning(get_string('importvalueinvalid', 'qbank_customfields', [
                    'field' => s($item['name']),
                    'value' => s($item['text']),
                ]));
                continue;
            }
            $instance->{$datacontroller->get_form_element_name()} = $value;
        }
        $customfieldhandler->instance_form_save($instance);
    }
}
