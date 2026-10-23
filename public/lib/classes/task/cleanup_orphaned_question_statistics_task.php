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
 * Task to clean up orphaned question statistics data.
 *
 * @package    core
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\task;

use core\local\cli\shutdown;


/**
 * Task to remove orphaned question response analysis, response counts and question statistics.
 */
class cleanup_orphaned_question_statistics_task extends adhoc_task {
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        self::cleanup_orphaned_question_statistics_data();
    }

    /**
     * Remove orphaned question statistics data.
     *
     * @return void
     */
    public static function cleanup_orphaned_question_statistics_data(): void {
        global $DB;

        $questionids = $DB->get_fieldset_sql(
            'SELECT DISTINCT qra.questionid
               FROM {question_response_analysis} qra
          LEFT JOIN {question} q ON q.id = qra.questionid
              WHERE q.id IS NULL
           ORDER BY qra.questionid'
        );

        foreach ($questionids as $questionid) {
            $transaction = $DB->start_delegated_transaction();
            \core_question\statistics\responses\analysis_for_actual_response::delete_cache_by_questionid((int) $questionid);
            \core_question\statistics\questions\calculated::delete_cache_by_questionid((int) $questionid);
            $transaction->allow_commit();

            // The task might be running for a long time. If a graceful exit is requested, queue this task again.
            if (shutdown::should_gracefully_exit()) {
                manager::queue_adhoc_task(new self());
                mtrace('Graceful exit requested, rescheduled cleanup_orphaned_question_statistics_task');
                break;
            }
        }

        // Clean up any leftover broken references.
        $DB->delete_records_select(
            'question_response_count',
            'NOT EXISTS (
                SELECT 1
                  FROM {question_response_analysis} qra
                 WHERE qra.id = {question_response_count}.analysisid
            )'
        );

        $DB->delete_records_select(
            'question_statistics',
            'NOT EXISTS (
                SELECT 1
                  FROM {question} q
                 WHERE q.id = {question_statistics}.questionid
            )'
        );
    }
}
