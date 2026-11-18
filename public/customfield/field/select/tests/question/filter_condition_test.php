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
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the dropdown menu field question bank filter condition.
 *
 * @package   customfield_select
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition::class)]
final class filter_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Dropdown menu fields offer their options to select from, with "any" or "none" join types.
     */
    public function test_create(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('select', ['options' => "Easy\nMedium\nHard"]);

        $condition = customfield_condition::create($field);

        $this->assertInstanceOf(filter_condition::class, $condition);
        $this->assertNull($condition->get_filter_class());
        $this->assertFalse($condition->allow_custom());
        $this->assertSame([datafilter::JOINTYPE_NONE, datafilter::JOINTYPE_ANY], $condition->get_join_list());
        $this->assertSame([
            ['value' => 1, 'title' => 'Easy'],
            ['value' => 2, 'title' => 'Medium'],
            ['value' => 3, 'title' => 'Hard'],
        ], $condition->get_initial_values());
    }

    /**
     * Provide filters and the questions they match.
     *
     * @return array
     */
    public static function build_query_provider(): array {
        return [
            'any' => ['', datafilter::JOINTYPE_ANY, [1, 3], ['q1', 'q3']],
            'none, including questions with no value' => ['', datafilter::JOINTYPE_NONE, [1, 3], ['q2', 'q4']],
            'any, with questions with no value using the default' => ['Medium', datafilter::JOINTYPE_ANY, [2], ['q2', 'q4']],
            'no options selected' => ['', datafilter::JOINTYPE_ANY, [], ['q1', 'q2', 'q3', 'q4']],
        ];
    }

    /**
     * Questions are matched on the selected option.
     *
     * @param string $defaultvalue The field's default option.
     * @param int $jointype
     * @param int[] $values The selected option indexes.
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('build_query_provider')]
    public function test_build_query(string $defaultvalue, int $jointype, array $values, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('select', [
            'options' => "Easy\nMedium\nHard",
            'defaultvalue' => $defaultvalue,
        ]);
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3', 'q4']);
        $this->set_question_customfield_value($field, $questionids['q1'], 1);
        $this->set_question_customfield_value($field, $questionids['q2'], 2);
        $this->set_question_customfield_value($field, $questionids['q3'], 3);

        $query = (new filter_condition($field))->build_query(['jointype' => $jointype, 'values' => $values]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }
}
