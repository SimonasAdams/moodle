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
 * Base class for handling import and export of plugin data
 *
 * Any plugin that stores additional data for a question should return an extension of this class from
 * plugin_feature::get_data_mapper().
 *
 * A plugin's data for a question is a list of nodes. Each node is an array with a 'name' key, and is either:
 * - a leaf, holding a piece of formatted text:
 *   - 'text' (string) The value.
 *   - 'format' (int) Optional FORMAT_* constant, defaults to FORMAT_PLAIN.
 *   - 'files' (\stored_file[]) Optional, export only. Files embedded in the text.
 *   - 'itemid' (int) Import only. The draft area holding the embedded files, only set if there are any.
 * - a branch, holding other nodes:
 *   - 'children' (array) A non-empty list of nodes.
 *
 * Names do not need to be unique, so a list of values or records can be represented by repeating a name.
 * Values should be portable between sites, so use things like usernames or idnumbers, not database ids.
 *
 * For example, a plugin storing a review status (a leaf) and a list of sources (repeated branches) for a question
 * could return these nodes:
 *
 * [
 *     ['name' => 'status', 'text' => 'approved'],
 *     ['name' => 'source', 'children' => [
 *         ['name' => 'title', 'text' => 'Physics textbook, chapter 3'],
 *         ['name' => 'notes', 'text' => '<p>Adapted from exercise 3.2.</p>', 'format' => FORMAT_HTML],
 *     ]],
 *     ['name' => 'source', 'children' => [
 *         ['name' => 'title', 'text' => 'Past exam paper, 2024'],
 *     ]],
 * ]
 *
 * @package   core_question
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_mapper_base {

    /**
     * Return array of additional question data stored by this plugin.
     *
     * It should always return a key for each question id passed in $questionids, even if there is no data for that question.
     *
     * @param int[] $questionids The questions we are fetching data for.
     * @return array [$questionid => array $nodes], with nodes as described in the class documentation.
     */
    public function get_question_data(array $questionids): array {
        return array_fill_keys($questionids, []);
    }

    /**
     * Save additional question data for this plugin.
     *
     * @param int $questionid The question we are importing data for
     * @param array $data The list of nodes to import, as described in the class documentation.
     * @throws \moodle_exception If the data could not be saved; the question import is rolled back.
     */
    public function save_question_data(int $questionid, array $data): void {
    }

}
