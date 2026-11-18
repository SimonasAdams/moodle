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

use PHPUnit\Framework\Attributes\CoversClass;
use qbank_viewquestiontype\type_condition;

/**
 * Unit tests for filter_condition_manager.
 *
 * @package   core_question
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(filter_condition_manager::class)]
final class filter_condition_manager_test extends \advanced_testcase {
    /**
     * get_conditions() returns an instance of each condition from the enabled qbank plugins, keyed by its key.
     */
    public function test_get_conditions(): void {
        $this->resetAfterTest();

        $conditions = filter_condition_manager::get_conditions();

        $this->assertArrayHasKey('qtype', $conditions);
        $this->assertInstanceOf(type_condition::class, $conditions['qtype']);
        foreach ($conditions as $key => $condition) {
            $this->assertInstanceOf(condition::class, $condition);
            $this->assertSame($key, $condition->get_key());
        }

        set_config('disabled', 1, 'qbank_viewquestiontype');
        $this->assertArrayNotHasKey('qtype', filter_condition_manager::get_conditions());
    }

    /**
     * The deprecated get_condition_classes() still returns the class of each condition, keyed by its key.
     */
    public function test_get_condition_classes(): void {
        $classes = filter_condition_manager::get_condition_classes();
        $this->assertDebuggingCalled();

        $this->assertSame(type_condition::class, $classes['qtype']);
    }

    /**
     * filter_invalid_values() lets each condition replace or remove its invalid values.
     */
    public function test_filter_invalid_values(): void {
        $this->resetAfterTest();
        $filterconditions = [
            'cat' => '5,1',
            'filter' => [
                'category' => [
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => [-1],
                ],
                'qtype' => [
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => ['truefalse'],
                ],
            ],
        ];

        $filtered = filter_condition_manager::filter_invalid_values($filterconditions);

        // The category no longer exists, so it is replaced with the default category.
        $this->assertSame(['5'], $filtered['filter']['category']['values']);
        $this->assertSame($filterconditions['filter']['qtype'], $filtered['filter']['qtype']);
    }
}
