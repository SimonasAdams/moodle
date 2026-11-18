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
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the checkbox field question bank filter condition.
 *
 * @package   customfield_checkbox
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition::class)]
final class filter_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Checkbox fields are filtered with the binary filter type, with no choice of join type.
     */
    public function test_create(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('checkbox');

        $condition = customfield_condition::create($field);

        $this->assertInstanceOf(filter_condition::class, $condition);
        $this->assertSame('core/datafilter/filtertypes/binary', $condition->get_filter_class());
        $this->assertSame([filter_condition::JOINTYPE_DEFAULT], $condition->get_join_list());
    }

    /**
     * Provide filters and the questions they match.
     *
     * @return array
     */
    public static function build_query_provider(): array {
        return [
            'checked' => [0, 1, ['q1']],
            'not checked, including questions with no value' => [0, 0, ['q2', 'q3']],
            'checked, with questions with no value checked by default' => [1, 1, ['q1', 'q3']],
            'not checked, with questions with no value checked by default' => [1, 0, ['q2']],
        ];
    }

    /**
     * Questions are matched on whether the box is checked, and questions with no value use the default.
     *
     * @param int $checkbydefault Whether the box is checked by default.
     * @param int $value The filter value, 1 for checked.
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('build_query_provider')]
    public function test_build_query(int $checkbydefault, int $value, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('checkbox', ['checkbydefault' => $checkbydefault]);
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3']);
        $this->set_question_customfield_value($field, $questionids['q1'], 1);
        $this->set_question_customfield_value($field, $questionids['q2'], 0);

        $query = customfield_condition::create($field)->build_query([
            'jointype' => filter_condition::JOINTYPE_DEFAULT,
            'values' => [$value],
        ]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }
}
