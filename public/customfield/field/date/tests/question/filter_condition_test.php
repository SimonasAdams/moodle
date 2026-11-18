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

namespace customfield_date\question;

use core\exception\moodle_exception;
use core_question\local\bank\customfield_condition;
use core_question\test\customfield_condition_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the date field question bank filter condition.
 *
 * @package   customfield_date
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition::class)]
#[CoversClass(\core_question\local\bank\datetime_condition_trait::class)]
final class filter_condition_test extends \advanced_testcase {
    use customfield_condition_test_trait;

    /**
     * Date fields are filtered with the datetime filter type, with no choice of join type.
     */
    public function test_create(): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('date');

        $condition = customfield_condition::create($field);

        $this->assertInstanceOf(filter_condition::class, $condition);
        $this->assertSame('core/datafilter/filtertypes/datetime', $condition->get_filter_class());
        $this->assertSame([filter_condition::JOINTYPE_DEFAULT], $condition->get_join_list());
        $this->assertCount(2, $condition->get_initial_values());
    }

    /**
     * Provide filters and the questions they match.
     *
     * The questions' dates are several days apart, so the result does not depend on the timezone.
     *
     * @return array
     */
    public static function build_query_provider(): array {
        return [
            'before' => [filter_condition::MODE_BEFORE, ['2024-01-01T00:00', '2024-01-10T00:00'], ['q1']],
            'after' => [filter_condition::MODE_AFTER, ['2024-01-20T00:00', '2024-02-01T00:00'], ['q3']],
            'between' => [filter_condition::MODE_BETWEEN, ['2024-01-10T00:00', '2024-01-20T00:00'], ['q2']],
            'between, including all dates' => [filter_condition::MODE_BETWEEN, ['2023-01-01T00:00', '2025-01-01T00:00'],
                ['q1', 'q2', 'q3']],
        ];
    }

    /**
     * Questions are matched on their date, and questions without a date never match.
     *
     * @param string $mode
     * @param string[] $values The after and before datetimes.
     * @param string[] $expected The names of the questions expected to match.
     */
    #[DataProvider('build_query_provider')]
    public function test_build_query(string $mode, array $values, array $expected): void {
        $this->resetAfterTest();
        $field = $this->create_question_customfield('date');
        $questionids = $this->create_test_questions(['q1', 'q2', 'q3', 'q4']);
        $this->set_question_customfield_value($field, $questionids['q1'], gmmktime(12, 0, 0, 1, 5, 2024));
        $this->set_question_customfield_value($field, $questionids['q2'], gmmktime(12, 0, 0, 1, 15, 2024));
        $this->set_question_customfield_value($field, $questionids['q3'], gmmktime(12, 0, 0, 1, 25, 2024));

        $query = customfield_condition::create($field)->build_query([
            'jointype' => filter_condition::JOINTYPE_DEFAULT,
            'values' => $values,
            'filteroptions' => ['mode' => $mode],
        ]);

        $this->assertSame($expected, $this->get_matching_questions($questionids, $query));
    }

    /**
     * An incomplete filter does not filter anything.
     */
    public function test_build_query_incomplete(): void {
        $this->resetAfterTest();
        $condition = customfield_condition::create($this->create_question_customfield('date'));

        $this->assertSame(['', []], $condition->build_query(['values' => ['2024-01-01T00:00', '2024-01-10T00:00']]));
    }

    /**
     * Provide invalid filters.
     *
     * @return array
     */
    public static function invalid_filter_provider(): array {
        return [
            'unknown mode' => ['sometime', ['2024-01-01T00:00', '2024-01-10T00:00'], 'invaliddatetimemode'],
            'reversed range' => [filter_condition::MODE_BETWEEN, ['2024-01-10T00:00', '2024-01-01T00:00'],
                'invaliddatetimebetween'],
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
        $condition = customfield_condition::create($this->create_question_customfield('date'));

        try {
            $condition->build_query(['values' => $values, 'filteroptions' => ['mode' => $mode]]);
            $this->fail('An exception should have been thrown.');
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }
}
