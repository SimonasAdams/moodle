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

namespace customfield_number\question;

use core\exception\moodle_exception;
use core_question\local\bank\customfield_condition;
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the number field question bank filter condition.
 *
 * @package   customfield_number
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition::class)]
final class filter_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Number fields are filtered with the number filter type, with no choice of join type.
     */
    public function test_create(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('number');

        $condition = customfield_condition::create($field);

        $this->assertInstanceOf(filter_condition::class, $condition);
        $this->assertSame('core/datafilter/filtertypes/number', $condition->get_filter_class());
        $this->assertSame([filter_condition::JOINTYPE_DEFAULT], $condition->get_join_list());
    }

    /**
     * Provide filters and the questions they match.
     *
     * @return array
     */
    public static function build_query_provider(): array {
        return [
            'equal' => ['', filter_condition::MODE_EQUAL, ['2.5', ''], ['q2']],
            'less than' => ['', filter_condition::MODE_LESSTHAN, ['2.5', ''], ['q1']],
            'greater than' => ['', filter_condition::MODE_GREATERTHAN, ['2.5', ''], ['q3']],
            'between, inclusive' => ['', filter_condition::MODE_BETWEEN, ['1', '2.5'], ['q1', 'q2']],
            'equal, with questions with no value using the default' => ['10', filter_condition::MODE_EQUAL, ['10', ''],
                ['q3', 'q4']],
        ];
    }

    /**
     * Questions are matched by comparing their value, and questions with no value or default never match.
     *
     * @param string $defaultvalue The field's default value.
     * @param string $mode
     * @param string[] $values The filter values.
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('build_query_provider')]
    public function test_build_query(string $defaultvalue, string $mode, array $values, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('number', ['defaultvalue' => $defaultvalue, 'decimalplaces' => 2]);
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3', 'q4']);
        $this->set_question_customfield_value($field, $questionids['q1'], 1);
        $this->set_question_customfield_value($field, $questionids['q2'], 2.5);
        $this->set_question_customfield_value($field, $questionids['q3'], 10);

        $query = customfield_condition::create($field)->build_query([
            'jointype' => filter_condition::JOINTYPE_DEFAULT,
            'values' => $values,
            'filteroptions' => ['mode' => $mode],
        ]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }

    /**
     * Provide invalid filters.
     *
     * @return array
     */
    public static function invalid_filter_provider(): array {
        return [
            'unknown mode' => ['about', ['1', ''], 'invalidmode'],
            'not a number' => [filter_condition::MODE_EQUAL, ['one', ''], 'invalidnum'],
            'missing upper limit' => [filter_condition::MODE_BETWEEN, ['1', ''], 'invalidnum'],
            'reversed range' => [filter_condition::MODE_BETWEEN, ['5', '1'], 'invalidnumberbetween'],
        ];
    }

    /**
     * An invalid filter throws an exception.
     *
     * @param string $mode
     * @param string[] $values
     * @param string $errorcode The expected exception's error code.
     */
    #[DataProvider('invalid_filter_provider')]
    public function test_build_query_invalid(string $mode, array $values, string $errorcode): void {
        $this->resetAfterTest();
        $condition = customfield_condition::create($this->create_question_customfield('number'));

        try {
            $condition->build_query(['values' => $values, 'filteroptions' => ['mode' => $mode]]);
            $this->fail('An exception should have been thrown.');
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }
}
