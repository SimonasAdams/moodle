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

/**
 * Tests question statistics cache deletion.
 *
 * @package    core_question
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/questionlib.php');

/**
 * Tests for response analysis cache deletion.
 *
 * @package    core_question
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \core_question\statistics\responses\analysis_for_actual_response
 */
final class analysis_for_actual_response_test extends advanced_testcase {
    /**
     * Test deleting cached response analysis by question id.
     *
     * @return void
     * @covers \core_question\statistics\responses\analysis_for_actual_response::delete_cache_by_questionid
     */
    public function test_delete_cache_by_questionid(): void {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);

        $hashcode = sha1('question-delete-cache-test');
        $otherhashcode = sha1('other-question-delete-cache-test');
        $now = time();

        $analysisid = $DB->insert_record('question_response_analysis', (object) [
            'hashcode' => $hashcode,
            'whichtries' => 'all',
            'timemodified' => $now,
            'questionid' => $question->id,
            'variant' => null,
            'subqid' => '1',
            'aid' => null,
            'response' => 'Answer',
            'credit' => 0.0,
        ]);

        $DB->insert_record('question_response_count', (object) [
            'analysisid' => $analysisid,
            'try' => 0,
            'rcount' => 1,
        ]);

        $otheranalysisid = $DB->insert_record('question_response_analysis', (object) [
            'hashcode' => $otherhashcode,
            'whichtries' => 'all',
            'timemodified' => $now,
            'questionid' => $question->id + 1,
            'variant' => null,
            'subqid' => '2',
            'aid' => null,
            'response' => 'Other answer',
            'credit' => 0.0,
        ]);

        $DB->insert_record('question_response_count', (object) [
            'analysisid' => $otheranalysisid,
            'try' => 0,
            'rcount' => 2,
        ]);

        \core_question\statistics\responses\analysis_for_actual_response::delete_cache_by_questionid($question->id);

        self::assertFalse($DB->record_exists('question_response_analysis', ['questionid' => $question->id]));
        self::assertFalse($DB->record_exists('question_response_count', ['analysisid' => $analysisid]));
        self::assertTrue($DB->record_exists('question_response_analysis', ['questionid' => $question->id + 1]));
        self::assertTrue($DB->record_exists('question_response_count', ['analysisid' => $otheranalysisid]));
    }

    /**
     * Test deleting cached response analysis by hash code.
     *
     * @return void
     * @covers \core_question\statistics\responses\analysis_for_actual_response::delete_cache_by_hashcode
     */
    public function test_delete_cache_by_hashcode(): void {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);

        $hashcode = sha1('question-delete-cache-test');
        $otherhashcode = sha1('other-question-delete-cache-test');
        $now = time();

        $analysisid = $DB->insert_record('question_response_analysis', (object) [
            'hashcode' => $hashcode,
            'whichtries' => 'all',
            'timemodified' => $now,
            'questionid' => $question->id,
            'variant' => null,
            'subqid' => '1',
            'aid' => null,
            'response' => 'Answer',
            'credit' => 0.0,
        ]);

        $DB->insert_record('question_response_count', (object) [
            'analysisid' => $analysisid,
            'try' => 0,
            'rcount' => 1,
        ]);

        $otheranalysisid = $DB->insert_record('question_response_analysis', (object) [
            'hashcode' => $otherhashcode,
            'whichtries' => 'all',
            'timemodified' => $now,
            'questionid' => $question->id,
            'variant' => null,
            'subqid' => '2',
            'aid' => null,
            'response' => 'Other answer',
            'credit' => 0.0,
        ]);

        $DB->insert_record('question_response_count', (object) [
            'analysisid' => $otheranalysisid,
            'try' => 0,
            'rcount' => 2,
        ]);

        \core_question\statistics\responses\analysis_for_actual_response::delete_cache_by_hashcode($hashcode);

        self::assertFalse($DB->record_exists('question_response_analysis', ['hashcode' => $hashcode]));
        self::assertFalse($DB->record_exists('question_response_count', ['analysisid' => $analysisid]));
        self::assertTrue($DB->record_exists('question_response_analysis', ['hashcode' => $otherhashcode]));
        self::assertTrue($DB->record_exists('question_response_count', ['analysisid' => $otheranalysisid]));
    }
}
