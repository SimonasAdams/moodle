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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the condition base class, and its support for conditions using the deprecated static methods.
 *
 * @package   core_question
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(condition::class)]
final class condition_test extends \advanced_testcase {
    /**
     * A condition which still implements the deprecated static methods works through the instance methods, with debugging.
     */
    public function test_legacy_condition(): void {
        $condition = new class extends condition {
            #[\Override]
            public function get_title() {
                return 'Legacy';
            }

            #[\Override]
            public static function get_condition_key() {
                return 'legacy';
            }

            #[\Override]
            public static function build_query_from_filter(array $filter): array {
                return ['q.id = :legacy', ['legacy' => $filter['values'][0]]];
            }
        };
        $filter = ['jointype' => condition::JOINTYPE_DEFAULT, 'values' => [5]];

        $this->assertSame('legacy', $condition->get_key());
        $this->assertDebuggingCalled();
        $this->assertSame($filter, $condition->find_filter(['legacy' => $filter, 'other' => []]));
        $this->assertDebuggingCalled();
        $this->assertSame(['q.id = :legacy', ['legacy' => 5]], $condition->build_query($filter));
        $this->assertDebuggingCalled();
    }

    /**
     * The deprecated static methods still work for a condition which implements the instance methods, with debugging.
     */
    public function test_deprecated_static_methods(): void {
        $condition = new class extends condition {
            #[\Override]
            public function get_title() {
                return 'Converted';
            }

            #[\Override]
            public function get_key(): string {
                return 'converted';
            }

            #[\Override]
            public function build_query(array $filter): array {
                return ['q.id = :converted', ['converted' => $filter['values'][0]]];
            }
        };
        $class = get_class($condition);
        $filter = ['jointype' => condition::JOINTYPE_DEFAULT, 'values' => [5]];

        $this->assertSame('converted', $class::get_condition_key());
        $this->assertDebuggingCalled();
        $this->assertSame($filter, $class::get_filter_from_list(['converted' => $filter, 'other' => []]));
        $this->assertDebuggingCalled();
        $this->assertSame(['q.id = :converted', ['converted' => 5]], $class::build_query_from_filter($filter));
        $this->assertDebuggingCalled();

        // The instance methods give the same results without debugging.
        $this->assertSame('converted', $condition->get_key());
        $this->assertSame($filter, $condition->find_filter(['converted' => $filter]));
        $this->assertSame(['q.id = :converted', ['converted' => 5]], $condition->build_query($filter));
    }

    /**
     * A condition must implement get_key() and build_query(), or the deprecated static methods.
     */
    public function test_condition_without_key_or_query(): void {
        $condition = new class extends condition {
            #[\Override]
            public function get_title() {
                return 'Incomplete';
            }
        };

        try {
            $condition->get_key();
            $this->fail('An exception should have been thrown.');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('must implement get_key()', $e->getMessage());
        }
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('must implement build_query()');
        $condition->build_query(['values' => []]);
    }
}
