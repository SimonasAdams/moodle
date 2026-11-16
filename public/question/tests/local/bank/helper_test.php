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

/**
 * Tests for the question bank helper class.
 *
 * @package    core_question
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(helper::class)]
final class helper_test extends \advanced_testcase {

    /**
     * Enabled plugins are returned as plugin_feature objects, indexed by name without the qbank_ prefix.
     */
    public function test_get_enabled_plugin_features(): void {
        $features = helper::get_enabled_plugin_features();

        $this->assertArrayHasKey('customfields', $features);
        $this->assertArrayNotHasKey('qbank_customfields', $features);
        $this->assertInstanceOf(\qbank_customfields\plugin_feature::class, $features['customfields']);
        $this->assertContainsOnlyInstancesOf(plugin_features_base::class, $features);
    }

    /**
     * Disabled plugins are not returned.
     */
    public function test_get_enabled_plugin_features_excludes_disabled(): void {
        $this->resetAfterTest();
        \core\plugininfo\qbank::enable_plugin('customfields', 0);

        $features = helper::get_enabled_plugin_features();

        $this->assertArrayNotHasKey('customfields', $features);
        $this->assertArrayHasKey('viewquestionname', $features);
    }
}
