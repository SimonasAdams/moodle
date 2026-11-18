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

namespace customfield_textarea\question;

use core_question\local\bank\customfield_condition;

/**
 * Question bank filter condition for a text area custom field, searching for keywords in the field's value.
 *
 * The search is on the stored value, which may contain HTML, in the same way as the question text filter.
 *
 * @package   customfield_textarea
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Simon Adams <simon.adams@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_condition extends customfield_condition {
    #[\Override]
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/keyword';
    }

    #[\Override]
    public function build_query(array $filter): array {
        return $this->build_keyword_query($filter);
    }
}
