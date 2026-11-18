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

namespace customfield_text\question;

use core\output\datafilter;
use core_question\local\bank\customfield_condition;
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the text field question bank filter condition.
 *
 * @package   customfield_text
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition::class)]
final class filter_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Text fields are filtered with the keyword filter type.
     */
    public function test_create(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text');

        $condition = customfield_condition::create($field);

        $this->assertInstanceOf(filter_condition::class, $condition);
        $this->assertSame('core/datafilter/filtertypes/keyword', $condition->get_filter_class());
    }

    /**
     * Provide filters and the questions they match.
     *
     * @return array
     */
    public static function build_query_provider(): array {
        return [
            'any' => [datafilter::JOINTYPE_ANY, ['easy', 'medium'], ['q1', 'q2']],
            'all' => [datafilter::JOINTYPE_ALL, ['very', 'hard'], ['q3']],
            'none' => [datafilter::JOINTYPE_NONE, ['easy'], ['q2', 'q3', 'q4']],
        ];
    }

    /**
     * Questions are matched on keywords in the field's value.
     *
     * @param int $jointype
     * @param string[] $keywords
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('build_query_provider')]
    public function test_build_query(int $jointype, array $keywords, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('text');
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3', 'q4']);
        $this->set_question_customfield_value($field, $questionids['q1'], 'Easy');
        $this->set_question_customfield_value($field, $questionids['q2'], 'Medium');
        $this->set_question_customfield_value($field, $questionids['q3'], 'Very hard');

        $query = customfield_condition::create($field)->build_query(['jointype' => $jointype, 'values' => $keywords]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }
}
