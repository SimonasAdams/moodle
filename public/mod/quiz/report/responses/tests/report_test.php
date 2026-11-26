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

namespace quiz_responses;

use mod_quiz\local\reports\attempts_report;
use mod_quiz\tests\question_helper_test_trait;
use question_attempt;
use quiz_first_or_all_responses_table;
use quiz_last_responses_table;
use quiz_responses_options;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/responses/report.php');

/**
 * Tests for the question name, question version and correctness columns of the quiz responses report.
 *
 * @package   quiz_responses
 * @copyright 2026 Catalyst IT Europe Ltd
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_last_responses_table::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_first_or_all_responses_table::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_responses_options::class)]
final class report_test extends \advanced_testcase {
    use question_helper_test_trait;

    /**
     * Data provider for {@see test_question_name_version_and_correctness_columns()}.
     *
     * @return array test cases.
     */
    public static function question_columns_provider(): array {
        return [
            'Last try, on screen' => [question_attempt::LAST_TRY, null],
            'Last try, CSV download' => [question_attempt::LAST_TRY, 'csv'],
            'All tries, on screen' => [question_attempt::ALL_TRIES, null],
            'All tries, CSV download' => [question_attempt::ALL_TRIES, 'csv'],
        ];
    }

    /**
     * The new columns show the question actually attempted, its version, and whether the response was correct.
     *
     * @param string $whichtries which tries to show, one of the question_attempt::*_TRY constants.
     * @param string|null $download the download format, or null when the report is shown on screen.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('question_columns_provider')]
    public function test_question_name_version_and_correctness_columns(string $whichtries, ?string $download): void {
        $this->resetAfterTest();
        [$quiz, $student1, $student2] = $this->create_quiz_with_attempts();

        $table = $this->get_report_table($quiz, $whichtries, $download);

        $rows = [];
        foreach ($table->rawdata as $row) {
            $rows[$row->userid] = $row;
        }
        $this->assertCount(2, $rows);

        // With first or all tries, a question that was never answered has no try to report on, as in the response column.
        $notanswered = $whichtries === question_attempt::LAST_TRY ? 'Not answered' : '-';
        $expected = [
            $student1->id => [
                1 => ['Random B', '2', 'Correct'],
                2 => ['Frog v2', '2', 'Correct'],
                3 => ['Essay', '1', 'Requires grading'],
            ],
            $student2->id => [
                1 => ['Random A', '1', 'Incorrect'],
                2 => ['Frog v2', '2', 'Partially correct'],
                3 => ['Essay', '1', $notanswered],
            ],
        ];
        foreach ($expected as $userid => $slots) {
            foreach ($slots as $slot => [$name, $version, $correctness]) {
                $row = $rows[$userid];
                $this->assertEquals($name, $table->other_cols('qname' . $slot, $row));
                $this->assertEquals($version, $table->other_cols('qversion' . $slot, $row));

                $cell = $table->other_cols('correctness' . $slot, $row);
                if ($download) {
                    $this->assertEquals($correctness, $cell);
                } else {
                    $this->assertEquals($correctness, trim(strip_tags($cell)));
                    if (!in_array($correctness, ['Requires grading', '-'])) {
                        // Graded questions also get the tick or cross icon on screen.
                        $this->assertStringContainsString('<i', $cell);
                    }
                }
            }
        }
    }

    /**
     * Data provider for {@see test_sort_by_question_name_version_and_correctness()}.
     *
     * @return array test cases.
     */
    public static function sort_columns_provider(): array {
        $cases = [];
        foreach ([question_attempt::LAST_TRY, question_attempt::ALL_TRIES] as $whichtries) {
            foreach (['qname1', 'qversion1', 'correctness2'] as $column) {
                // Student 2 has Random A (v1), and a partially correct answer to slot 2, so comes first ascending.
                $cases["{$whichtries}, {$column} ascending"] = [$whichtries, $column, SORT_ASC, ['student2', 'student1']];
                $cases["{$whichtries}, {$column} descending"] = [$whichtries, $column, SORT_DESC, ['student1', 'student2']];
            }
        }
        return $cases;
    }

    /**
     * The question name, version and correctness columns can be sorted on.
     *
     * @param string $whichtries which tries to show, one of the question_attempt::*_TRY constants.
     * @param string $column the column to sort on.
     * @param int $order SORT_ASC or SORT_DESC.
     * @param string[] $expectedorder the usernames of the students, in the order expected.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sort_columns_provider')]
    public function test_sort_by_question_name_version_and_correctness(
        string $whichtries,
        string $column,
        int $order,
        array $expectedorder,
    ): void {
        $this->resetAfterTest();
        [$quiz, $student1, $student2] = $this->create_quiz_with_attempts();
        $usernames = [$student1->id => 'student1', $student2->id => 'student2'];

        $table = $this->get_report_table($quiz, $whichtries, null, $column, $order);

        $actualorder = array_map(fn($row) => $usernames[$row->userid], array_values($table->rawdata));
        $this->assertEquals($expectedorder, $actualorder);
    }

    /**
     * Create a quiz with three questions, and a finished attempt by each of two students.
     *
     * Slot 1 is a random question, slot 2 a short-answer question with two versions and slot 3 an essay.
     * Student 1 gets "Random B" (version 2) in slot 1 and answers everything correctly. Student 2 gets "Random A"
     * (version 1) in slot 1 and answers it wrongly, gives a partially correct answer to slot 2 and skips the essay.
     *
     * @return array the quiz, then the two students.
     */
    protected function create_quiz_with_attempts(): array {
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');

        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 3,
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');

        // Slot 1: a random question. Each student is given a different question, in a different version.
        // It must be slot 1, as quiz_start_new_attempt() looks up forced random questions by $quba->next_slot_number(),
        // which is 1 at that point.
        $randomcat = $questiongenerator->create_question_category();
        $randoma = $questiongenerator->create_question('truefalse', null, ['category' => $randomcat->id, 'name' => 'Random A']);
        $randomb = $questiongenerator->create_question('truefalse', null, ['category' => $randomcat->id, 'name' => 'Random B']);
        $randomb = $questiongenerator->update_question($randomb, null, ['name' => 'Random B']);
        $this->add_random_questions($quiz->id, 0, $randomcat->id, 1);

        // Slot 2: a short-answer question with two versions, so the latest version is attempted.
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', 'frogtoad', ['category' => $cat->id, 'name' => 'Frog v1']);
        $questiongenerator->update_question($saq, 'frogtoad', ['name' => 'Frog v2']);
        quiz_add_quiz_question($saq->id, $quiz);

        // Slot 3: an essay, which needs manual grading.
        $essay = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id, 'name' => 'Essay']);
        quiz_add_quiz_question($essay->id, $quiz);

        $this->setUser($student1);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student1->id, [1 => $randomb->id]);
        $quizgenerator->submit_responses($attempt->id, [
            1 => 'True',
            2 => 'frog',
            3 => 'My essay',
        ], false, true);

        $this->setUser($student2);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student2->id, [1 => $randoma->id]);
        $quizgenerator->submit_responses($attempt->id, [
            1 => 'False',
            2 => 'toad',
        ], false, true);

        return [$quiz, $student1, $student2];
    }

    /**
     * Build and query a responses report table with the question name, version and correctness columns shown.
     *
     * @param stdClass $quiz the quiz.
     * @param string $whichtries which tries to show, one of the question_attempt::*_TRY constants.
     * @param string|null $download the download format, or null when the report is shown on screen.
     * @param string $sortcolumn the column to sort on.
     * @param int $sortorder SORT_ASC or SORT_DESC.
     * @return quiz_last_responses_table the table, after the data has been loaded.
     */
    protected function get_report_table(
        stdClass $quiz,
        string $whichtries,
        ?string $download,
        string $sortcolumn = 'uniqueid',
        int $sortorder = SORT_ASC,
    ): quiz_last_responses_table {
        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $studentsjoins = get_enrolled_with_capabilities_join($context, '', ['mod/quiz:attempt', 'mod/quiz:reviewmyattempts']);

        $options = new quiz_responses_options('responses', $quiz, $cm, null);
        $options->attempts = attempts_report::ENROLLED_WITH;
        $options->showqname = true;
        $options->showqversion = true;
        $options->showcorrectness = true;
        $options->whichtries = $whichtries;

        $questions = quiz_report_get_significant_questions($quiz);
        $columns = ['fullname'];
        foreach (array_keys($questions) as $slot) {
            array_push($columns, 'qname' . $slot, 'qversion' . $slot, 'correctness' . $slot);
        }

        if ($whichtries === question_attempt::LAST_TRY) {
            $tableclass = quiz_last_responses_table::class;
        } else {
            $tableclass = quiz_first_or_all_responses_table::class;
        }
        $table = new $tableclass(
            $quiz,
            $context,
            quiz_report_qm_filter_select($quiz),
            $options,
            new \core\dml\sql_join(),
            $studentsjoins,
            $questions,
            null,
        );
        $table->download = $download; // Cannot call the is_downloading API, because it sends headers.
        $table->define_columns($columns);
        $table->sortable(true, $sortcolumn, $sortorder);
        $table->define_baseurl(new \moodle_url('/mod/quiz/report.php'));
        $table->setup();
        $table->setup_sql_queries($studentsjoins);
        $table->query_db(30, false);

        return $table;
    }
}
