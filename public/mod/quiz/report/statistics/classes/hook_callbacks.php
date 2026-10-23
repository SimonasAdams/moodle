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
namespace quiz_statistics;

use core\dml\sql_join;
use core_question\statistics\responses\analysis_for_actual_response;
use mod_quiz\hook\attempt_state_changed;
use mod_quiz\hook\structure_modified;
use mod_quiz\quiz_attempt;
use quiz_statistics\task\recalculate;

/**
 * Hook callbacks
 *
 * @package   quiz_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Clear the statistics cache for the quiz where the structure was modified.
     *
     * @param structure_modified $hook The structure_modified hook containing the new structure.
     * @return void
     */
    public static function quiz_structure_modified(structure_modified $hook) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/report/statistics/statisticslib.php');
        require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');
        $quiz = $hook->get_structure()->get_quiz();
        $qubaids = quiz_statistics_qubaids_condition(
            $quiz->id,
            new sql_join(),
            $quiz->grademethod
        );

        analysis_for_actual_response::delete_cache_by_hashcode($qubaids->get_hash_code());
        \core_question\statistics\questions\calculated::delete_cache_by_hashcode($qubaids->get_hash_code());
    }

    /**
     * Queue a statistics recalculation when an attempt is submitted or deleting.
     *
     * @param attempt_state_changed $hook
     * @return bool True if a task was queued.
     */
    public static function quiz_attempt_submitted_or_deleted(attempt_state_changed $hook): bool {
        $originalattempt = $hook->get_original_attempt();
        $updatedattempt = $hook->get_updated_attempt();
        if (is_null($updatedattempt) || $updatedattempt->state === quiz_attempt::FINISHED) {
            // Only recalculate on deletion or submission.
            recalculate::queue_future_run($originalattempt->quiz);
            return true;
        }
        return false;
    }
}
