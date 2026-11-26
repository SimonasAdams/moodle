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

use mod_quiz\local\reports\attempts_report_table;
use mod_quiz\quiz_attempt;

/**
 * This is a table subclass for displaying the quiz responses report.
 *
 * @copyright 2008 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_last_responses_table extends attempts_report_table {

    /**
     * Constructor
     * @param stdClass $quiz
     * @param context $context
     * @param string $qmsubselect
     * @param quiz_responses_options $options
     * @param \core\dml\sql_join $groupstudentsjoins
     * @param \core\dml\sql_join $studentsjoins
     * @param array $questions
     * @param moodle_url $reporturl
     */
    public function __construct($quiz, $context, $qmsubselect, quiz_responses_options $options,
            \core\dml\sql_join $groupstudentsjoins, \core\dml\sql_join $studentsjoins, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-responses-report', $quiz, $context,
                $qmsubselect, $options, $groupstudentsjoins, $studentsjoins, $questions, $reporturl);
    }

    public function build_table() {
        if (!$this->rawdata) {
            return;
        }

        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    public function col_sumgrades($attempt) {
        if ($attempt->state != quiz_attempt::FINISHED) {
            return '-';
        }

        $grade = quiz_rescale_grade($attempt->sumgrades, $this->quiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        $gradehtml = '<a href="review.php?q=' . $this->quiz->id . '&amp;attempt=' .
                $attempt->attempt . '">' . $grade . '</a>';
        return $gradehtml;
    }

    public function data_col($slot, $field, $attempt) {
        if ($attempt->usageid == 0) {
            return '-';
        }

        $value = $this->field_from_extra_data($attempt, $slot, $field);

        if (is_null($value)) {
            $summary = '-';
        } else {
            $summary = trim($value);
        }

        if ($field == 'questionname') {
            // Question names may use filters such as multilang, and are already escaped by format_string.
            return format_string($summary, true, ['context' => $this->context]);
        }

        if ($this->is_downloading() && $this->is_downloading() != 'html') {
            return $summary;
        }
        $summary = s($summary);

        if ($this->is_downloading() || $field != 'responsesummary') {
            return $summary;
        }

        return $this->make_review_link($summary, $attempt, $slot);
    }

    /**
     * Column text from the extra data loaded in load_extra_data(), before html formatting etc.
     *
     * @param stdClass $attempt
     * @param int $slot
     * @param string $field
     * @return string
     */
    protected function field_from_extra_data($attempt, $slot, $field) {
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }
        return $this->lateststeps[$attempt->usageid][$slot]->$field;
    }

    public function other_cols($colname, $attempt) {
        if (preg_match('/^question(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionsummary', $attempt);

        } else if (preg_match('/^response(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'responsesummary', $attempt);

        } else if (preg_match('/^right(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'rightanswer', $attempt);
        } else if (preg_match('/^qname(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionname', $attempt);
        } else if (preg_match('/^qversion(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionversion', $attempt);
        } else if (preg_match('/^correctness(\d+)$/', $colname, $matches)) {
            return $this->correctness_col($attempt, $matches[1]);

        } else {
            return parent::other_cols($colname, $attempt);
        }
    }

    /**
     * Text (and, when not downloading, an icon) saying whether the response in a slot was correct.
     *
     * @param stdClass $attempt the row data.
     * @param int $slot the slot of the question.
     * @return string the cell contents.
     */
    protected function correctness_col($attempt, $slot) {
        if ($attempt->usageid == 0 || !$this->has_step_data($attempt, $slot)) {
            return '-';
        }

        $state = $this->slot_state($attempt, $slot);
        if ($state === null) {
            return '-';
        }

        // The same text as the question review page, e.g. Correct, Incorrect, Not answered or Requires grading.
        $text = $state->default_string(true);
        if ($this->is_downloading()) {
            return $text;
        }

        // Use the same icon as the response column.
        $icon = '';
        if ($state->is_finished() && $state != question_state::$needsgrading) {
            $icon = $this->icon_for_fraction($this->slot_fraction($attempt, $slot));
        }
        return $icon . $text;
    }

    /**
     * Is there any data for the question in this slot for this table row?
     *
     * @param stdClass $attempt the row data.
     * @param int $slot the slot of the question.
     * @return bool true if there is data for this slot.
     */
    protected function has_step_data($attempt, $slot) {
        return isset($this->lateststeps[$attempt->usageid][$slot]);
    }

    /**
     * Load the latest steps, and the name and version of each question that was attempted, if they will be shown.
     */
    protected function load_extra_data() {
        parent::load_extra_data();

        if (!$this->options->showqname && !$this->options->showqversion) {
            return;
        }

        $questionids = [];
        foreach ($this->lateststeps as $slots) {
            foreach ($slots as $step) {
                $questionids[$step->questionid] = $step->questionid;
            }
        }
        if (!$questionids) {
            return;
        }

        $this->add_question_name_and_version($questionids);
    }

    /**
     * Add the name and version of the question attempted to each step loaded in {@see load_extra_data()}.
     *
     * The question attempted may differ between attempts, for example when using random questions.
     *
     * @param int[] $questionids ids of the questions attempted.
     */
    protected function add_question_name_and_version(array $questionids) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $questions = $DB->get_records_sql("
                SELECT q.id, q.name, qv.version
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                 WHERE q.id $insql", $params);

        foreach ($this->lateststeps as $slots) {
            foreach ($slots as $step) {
                $question = $questions[$step->questionid] ?? null;
                $step->questionname = $question->name ?? null;
                $step->questionversion = $question->version ?? null;
            }
        }
    }

    protected function requires_extra_data() {
        return true;
    }

    protected function is_latest_step_column($column) {
        if (preg_match('/^(?:question|response|right|qname|qversion|correctness)([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Get any fields that might be needed when sorting on date for a particular slot.
     * @param int    $slot  the slot for the column we want.
     * @param string $alias the table alias for latest state information relating to that slot.
     * @return string sql fragment to alias fields.
     */
    protected function get_required_latest_state_fields($slot, $alias) {
        global $DB;
        return $DB->sql_order_by_text("{$alias}.questionsummary") . " AS question{$slot},
                " . $DB->sql_order_by_text("{$alias}.rightanswer") . " AS right{$slot},
                " . $DB->sql_order_by_text("{$alias}.responsesummary") . " AS response{$slot},
                (SELECT q.name
                   FROM {question} q
                  WHERE q.id = {$alias}.questionid) AS qname{$slot},
                (SELECT qv.version
                   FROM {question_versions} qv
                  WHERE qv.questionid = {$alias}.questionid) AS qversion{$slot},
                {$alias}.fraction AS correctness{$slot}";
    }
}
