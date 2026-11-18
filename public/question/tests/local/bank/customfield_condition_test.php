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

namespace core_question\local\bank;

use core\exception\coding_exception;
use core\output\datafilter;
use core_customfield\field_controller;
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the customfield_condition base class.
 *
 * @package   core_question
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(customfield_condition::class)]
#[CoversClass(condition::class)]
final class customfield_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Create a keyword condition for the field, without depending on a field type plugin's own condition.
     *
     * @param field_controller $field
     * @return customfield_condition
     */
    private function create_keyword_condition(field_controller $field): customfield_condition {
        return new class ($field) extends customfield_condition {
            /**
             * Make the protected constructor public, as the condition is otherwise only created through create().
             *
             * @param field_controller $field
             */
            public function __construct(field_controller $field) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
                parent::__construct($field);
            }

            #[\Override]
            public function build_query(array $filter): array {
                return $this->build_keyword_query($filter);
            }
        };
    }

    /**
     * The key, title and filter lookup are specific to the field.
     */
    public function test_condition_identifies_field(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text', [], 'colour');
        $condition = $this->create_keyword_condition($field);

        $this->assertSame('customfield_colour', $condition->get_key());
        $this->assertSame('Colour', $condition->get_title());
        $filter = ['name' => 'customfield_colour', 'jointype' => datafilter::JOINTYPE_ANY, 'values' => ['red']];
        $this->assertSame($filter, $condition->find_filter(['customfield_colour' => $filter, 'qtype' => []]));
        $this->assertNull($condition->find_filter(['customfield' => $filter]));
    }

    /**
     * The static query builder cannot be used, as it does not know the field.
     */
    public function test_build_query_from_filter_throws(): void {
        $this->expectException(coding_exception::class);
        customfield_condition::build_query_from_filter(['values' => ['red']]);
    }

    /**
     * Provide keyword filters and the questions they match.
     *
     * @return array
     */
    public static function keyword_provider(): array {
        return [
            'any of one keyword' => [datafilter::JOINTYPE_ANY, ['red'], ['q1', 'q3']],
            'any of two keywords' => [datafilter::JOINTYPE_ANY, ['red', 'blue'], ['q1', 'q2', 'q3']],
            'all keywords' => [datafilter::JOINTYPE_ALL, ['red', 'dark'], ['q3']],
            'none of the keywords, including questions with no value' => [datafilter::JOINTYPE_NONE, ['red'], ['q2', 'q4']],
            'wildcards are matched literally' => [datafilter::JOINTYPE_ANY, ['%'], []],
        ];
    }

    /**
     * Keyword queries match the field's value ignoring case, and treat a question with no value as empty.
     *
     * @param int $jointype
     * @param string[] $keywords
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('keyword_provider')]
    public function test_build_keyword_query(int $jointype, array $keywords, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text');
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3', 'q4']);
        $this->set_question_customfield_value($field, $questionids['q1'], 'Red');
        $this->set_question_customfield_value($field, $questionids['q2'], 'Blue');
        $this->set_question_customfield_value($field, $questionids['q3'], 'Dark red');

        $condition = $this->create_keyword_condition($field);
        $query = $condition->build_query(['jointype' => $jointype, 'values' => $keywords]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }

    /**
     * Questions with no stored value are compared using the field's default value.
     */
    public function test_default_value_used_for_questions_without_value(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text', ['defaultvalue' => 'Green']);
        $questionids = $this->create_test_questions(['q1', 'q2']);
        $this->set_question_customfield_value($field, $questionids['q1'], 'Red');

        $condition = $this->create_keyword_condition($field);
        $query = $condition->build_query(['jointype' => datafilter::JOINTYPE_ANY, 'values' => ['green']]);

        $this->assertSame(['q2'], $this->get_matching_questions($questionids, $query));
    }

    /**
     * Conditions on several fields can be combined in one query, as each query uses unique placeholders.
     */
    public function test_conditions_combine(): void {
        $this->resetAfterTest();
        $colour = $this->create_question_customfield('text', [], 'colour');
        $size = $this->create_question_customfield('text', [], 'size');
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3']);
        $this->set_question_customfield_value($colour, $questionids['q1'], 'Red');
        $this->set_question_customfield_value($size, $questionids['q1'], 'Large');
        $this->set_question_customfield_value($colour, $questionids['q2'], 'Red');
        $this->set_question_customfield_value($size, $questionids['q2'], 'Small');
        $this->set_question_customfield_value($colour, $questionids['q3'], 'Blue');
        $this->set_question_customfield_value($size, $questionids['q3'], 'Large');

        $colourquery = $this->create_keyword_condition($colour)->build_query(
            ['jointype' => datafilter::JOINTYPE_ANY, 'values' => ['red']],
        );
        $sizequery = $this->create_keyword_condition($size)->build_query(
            ['jointype' => datafilter::JOINTYPE_ANY, 'values' => ['large']],
        );

        $this->assertSame(['q1'], $this->get_matching_questions($questionids, $colourquery, $sizequery));
    }

    /**
     * Only values stored for questions are used, not values of the same field stored for another component and area.
     */
    public function test_values_from_other_areas_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text');
        $questionids = $this->create_test_questions(['q1']);
        $this->set_question_customfield_value($field, $questionids['q1'], 'Red');
        // A row for the same field and instance id from another area, such as a shared field used elsewhere.
        $record = $DB->get_record('customfield_data', ['fieldid' => $field->get('id'), 'instanceid' => $questionids['q1']]);
        unset($record->id);
        $record->component = 'core_course';
        $record->area = 'course';
        $record->charvalue = 'Blue';
        $DB->insert_record('customfield_data', $record);

        $condition = $this->create_keyword_condition($field);
        $query = $condition->build_query(['jointype' => datafilter::JOINTYPE_ANY, 'values' => ['red']]);

        $this->assertSame(['q1'], $this->get_matching_questions($questionids, $query));
    }
}
