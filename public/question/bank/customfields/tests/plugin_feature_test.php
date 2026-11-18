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

use core\output\datafilter;
use core_question\local\bank\condition;
use core_question\local\bank\question_edit_contexts;
use core_question\local\bank\random_question_loader;
use core_question\local\bank\view;
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use qbank_customfields\customfield\question_handler;

/**
 * Unit tests for the qbank_customfields plugin features.
 *
 * @package   qbank_customfields
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(plugin_feature::class)]
final class plugin_feature_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Create a question bank with a question category.
     *
     * @return array [\stdClass $course, \stdClass $cm, \stdClass $category]
     */
    private function create_question_bank(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = $generator->get_plugin_generator('core_question')->create_question_category(['contextid' => $context->id]);
        return [$course, get_coursemodule_from_id('qbank', $qbank->cmid), $category];
    }

    /**
     * Create a question bank view.
     *
     * @param \stdClass $course
     * @param \stdClass $cm
     * @param \stdClass $category
     * @param array $filter Filters to apply, in addition to the category.
     * @return view
     */
    private function create_view(\stdClass $course, \stdClass $cm, \stdClass $category, array $filter = []): view {
        $context = \context_module::instance($cm->id);
        $params = [
            'qpage' => 0,
            'qperpage' => 20,
            'tabname' => 'editq',
            'cat' => $category->id . ',' . $context->id,
            'filter' => [
                'category' => [
                    'name' => 'category',
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => [$category->id],
                    'filteroptions' => [],
                ],
            ] + $filter,
            'jointype' => datafilter::JOINTYPE_ALL,
        ];
        return new view(new question_edit_contexts($context), new \moodle_url('/'), $course, $cm, $params, ['cmid' => $cm->id]);
    }

    /**
     * Get the keys of the custom field filters.
     *
     * @param condition[] $conditions
     * @return string[]
     */
    private function get_keys(array $conditions): array {
        $keys = array_map(fn(condition $condition) => $condition->get_key(), $conditions);
        sort($keys);
        return $keys;
    }

    /**
     * Only fields the user can see in the question bank are offered as filters, but all are used without a view.
     */
    public function test_get_question_filters_visibility(): void {
        $this->resetAfterTest();
        $this->create_question_customfield('text', ['visibility' => question_handler::VISIBLETOALL], 'everyone');
        $this->create_question_customfield('text', ['visibility' => question_handler::VISIBLETOTEACHERS], 'teachers');
        $this->create_question_customfield('text', ['visibility' => question_handler::NOTVISIBLE], 'nobody');
        [$course, $cm, $category] = $this->create_question_bank();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $feature = new plugin_feature();

        $this->assertSame(
            ['customfield_everyone', 'customfield_teachers'],
            $this->get_keys($feature->get_question_filters($this->create_view($course, $cm, $category))),
        );

        $coursecontext = \context_course::instance($course->id);
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->role_assign($roleid, $teacher->id, $coursecontext);
        assign_capability('qbank/customfields:viewhiddencustomfields', CAP_PROHIBIT, $roleid, $coursecontext);
        $this->assertSame(
            ['customfield_everyone'],
            $this->get_keys($feature->get_question_filters($this->create_view($course, $cm, $category))),
        );

        $this->assertSame(
            ['customfield_everyone', 'customfield_nobody', 'customfield_teachers'],
            $this->get_keys($feature->get_question_filters()),
        );
    }

    /**
     * Shared fields have no visibility setting for questions, so they are not offered as filters.
     */
    public function test_get_question_filters_excludes_shared_fields(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $sharedcategory = $generator->create_category(['component' => 'core_customfield', 'area' => 'shared']);
        $generator->create_field(['categoryid' => $sharedcategory->get('id'), 'type' => 'text', 'shortname' => 'shared']);
        $DB->insert_record('customfield_shared', [
            'categoryid' => $sharedcategory->get('id'),
            'component' => 'qbank_customfields',
            'area' => 'question',
            'itemid' => 0,
        ]);
        $this->create_question_customfield('text', [], 'question');
        \core_customfield\handler::reset_caches();

        $this->assertSame(['customfield_question'], $this->get_keys((new plugin_feature())->get_question_filters()));
    }

    /**
     * The question bank shows only the questions matching a custom field filter.
     */
    public function test_question_bank_view_filters_on_custom_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_question_customfield('select', ['options' => "Easy\nHard"], 'difficulty');
        [$course, $cm, $category] = $this->create_question_bank();
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        /** @var \qbank_customfields_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('qbank_customfields');
        foreach (['Easy question' => 'Easy', 'Hard question' => 'Hard'] as $name => $difficulty) {
            $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id, 'name' => $name]);
            $generator->create_question_custom_field_value([
                'questionid' => $question->id,
                'field' => 'difficulty',
                'value' => $difficulty,
            ]);
        }

        $view = $this->create_view($course, $cm, $category, [
            'customfield_difficulty' => [
                'name' => 'customfield_difficulty',
                'jointype' => datafilter::JOINTYPE_ANY,
                'values' => [2],
                'filteroptions' => [],
            ],
        ]);
        ob_start();
        $view->display();
        $html = ob_get_clean();

        $this->assertStringContainsString('Hard question', $html);
        $this->assertStringNotContainsString('Easy question', $html);
    }

    /**
     * Random questions are selected using saved custom field filters.
     */
    public function test_random_question_loader_filters_on_custom_field(): void {
        $this->resetAfterTest();
        $this->create_question_customfield('number', [], 'marks');
        $generator = $this->getDataGenerator();
        $category = $generator->get_plugin_generator('core_question')->create_question_category();
        $questionids = [];
        foreach ([1, 2, 3] as $marks) {
            $questionids[$marks] = $generator->get_plugin_generator('core_question')->create_question(
                'truefalse',
                null,
                ['category' => $category->id],
            )->id;
            $generator->get_plugin_generator('qbank_customfields')->create_question_custom_field_value([
                'questionid' => $questionids[$marks],
                'field' => 'marks',
                'value' => $marks,
            ]);
        }

        $filters = [
            'category' => [
                'jointype' => condition::JOINTYPE_DEFAULT,
                'values' => [$category->id],
                'filteroptions' => ['includesubcategories' => false],
            ],
            'customfield_marks' => [
                'name' => 'customfield_marks',
                'jointype' => condition::JOINTYPE_DEFAULT,
                'values' => ['2', ''],
                'filteroptions' => ['mode' => 'greaterthan'],
            ],
        ];
        $loader = new random_question_loader(new \qubaid_list([]));

        $this->assertEquals([$questionids[3]], array_keys($loader->get_filtered_questions($filters)));
    }
}
