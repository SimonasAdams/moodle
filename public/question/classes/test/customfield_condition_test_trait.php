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

namespace core_question\test;

use core_customfield\field_controller;

/**
 * Helpers for testing question bank filter conditions on question custom fields.
 *
 * For use in classes extending advanced_testcase.
 *
 * @package   core_question
 * @category  test
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait customfield_condition_test_trait {
    /**
     * Create a question custom field.
     *
     * The field is visible to everyone in the question bank, unless the configdata sets another visibility.
     *
     * @param string $type The field type, for example 'text'.
     * @param array $configdata The field configuration.
     * @param string $shortname The field shortname.
     * @return field_controller
     */
    protected function create_question_customfield(
        string $type,
        array $configdata = [],
        string $shortname = 'testfield',
    ): field_controller {
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => 'qbank_customfields', 'area' => 'question']);
        return $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => $type,
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'configdata' => $configdata,
        ]);
    }

    /**
     * Create questions with the given names in a new question bank.
     *
     * @param string[] $names The question names.
     * @return int[] The question ids, keyed by name.
     */
    protected function create_test_questions(array $names): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($qbank->cmid)->id]);
        $questionids = [];
        foreach ($names as $name) {
            $questionids[$name] = $questiongenerator->create_question('truefalse', null, [
                'category' => $category->id,
                'name' => $name,
            ])->id;
        }
        return $questionids;
    }

    /**
     * Store a question's value for a custom field.
     *
     * @param field_controller $field The field.
     * @param int $questionid The question id.
     * @param mixed $value The value, as it would be submitted from the question form, without any editor wrapping.
     */
    protected function set_question_customfield_value(field_controller $field, int $questionid, mixed $value): void {
        if ($field->get('type') === 'textarea') {
            $value = ['text' => $value, 'format' => FORMAT_HTML];
        }
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $generator->add_instance_data($field, $questionid, $value);
    }

    /**
     * Get the names of the given questions that match all the queries.
     *
     * @param int[] $questionids The question ids to check, keyed by name.
     * @param array ...$queries Queries from condition::build_query(), in the form [string $where, array $params].
     * @return string[] The matching question names, sorted.
     */
    protected function get_matching_questions(array $questionids, array ...$queries): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $where = "q.id {$insql}";
        foreach ($queries as [$querywhere, $queryparams]) {
            if ($querywhere !== '') {
                $where .= " AND ({$querywhere})";
                $params = array_merge($params, $queryparams);
            }
        }
        $names = $DB->get_fieldset_sql("SELECT q.name FROM {question} q WHERE {$where}", $params);
        sort($names);
        return $names;
    }
}
