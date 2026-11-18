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

use core\output\datafilter;
use restore_questions_activity_structure_step;
use stdClass;

/**
 * An abstract class for filtering/searching questions.
 *
 * @package    core_question
 * @copyright  2013 Ray Morris
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition {
    /** @var int The default filter type */
    const JOINTYPE_DEFAULT = datafilter::JOINTYPE_ANY;

    /** @var ?array Filter properties for this condition. */
    public ?array $filter;

    /** @var string SQL fragment to add to the where clause. */
    protected string $where = '';

    /** @var array query param used in where. */
    protected array $params = [];

    /**
     * Return title of the condition
     *
     * @return string title of the condition
     */
    abstract public function get_title();

    /**
     * Return the Javascript filter class to provide the UI for this condition.
     *
     * If left as null, this will use the default core/datafilter/filtertype class. Otherwise, override it to return
     * the full path to the Javascript module path for the class.
     *
     * @return ?string filter class
     */
    public function get_filter_class() {
        return null;
    }

    /**
     * Extract the required filter from the provided question bank view and set the initial values.
     *
     * This will look for the filter matching {@see get_key()} in the view's current filter parameter.
     * If the filter is not being initialised to display the question bank UI (for example, to resolve a list of questions matching
     * a set of filters), then the `$qbank` argument may be null, and any usage of it to set the initial filter state is skipped.
     *
     * @param ?view $qbank The question bank view the filter is being rendered for. This may only be used for setting the
     *     initial state of the filter.
     */
    public function __construct(?view $qbank = null) {
        if (is_null($qbank)) {
            return;
        }
        $this->filter = $this->find_filter($qbank->get_pagevars('filter'));
        // Build where and params.
        [$this->where, $this->params] = $this->filter ? $this->build_query($this->filter) : ['', []];
    }

    /**
     * Whether customisation is allowed.
     *
     * @return bool
     */
    public function allow_custom() {
        return true;
    }

    /**
     * Whether multiple values are allowed .
     *
     * @return bool
     */
    public function allow_multiple() {
        return true;
    }

    /**
     * Initial values of the condition
     *
     * @return array
     */
    public function get_initial_values() {
        return [];
    }

    /**
     * Extra data specific to this condition.
     *
     * @return \stdClass
     */
    public function get_filteroptions(): \stdClass {
        return (object)[];
    }

    /**
     * Whether empty value is allowed
     *
     * @return bool
     */
    public function allow_empty() {
        return true;
    }

    /**
     * Whether this filter is required - if so it cannot be removed from the list of filters.
     *
     * @return bool
     */
    public function is_required(): bool {
        return false;
    }

    /**
     * Return this condition class
     *
     * @return string
     */
    public function get_condition_class() {
        return get_class($this);
    }

    /**
     * Each condition will need a unique key to be identified and sequenced by the api.
     *
     * @deprecated since Moodle 6.0 MDL-84404
     * @see self::get_key()
     * @return string
     */
    #[\core\attribute\deprecated('condition::get_key()', since: '6.0', mdl: 'MDL-84404')]
    public static function get_condition_key() {
        \core\deprecation::emit_deprecation_if_present([self::class, __FUNCTION__]);
        return (new static())->get_key();
    }

    /**
     * Get the key which identifies this condition, and which its filter is stored under.
     *
     * Each condition will need a unique key to be identified and sequenced by the api.
     * Use a unique string for the condition identifier, use string directly, dont need to use language pack.
     * Using language pack might break the filter object for multilingual support.
     * When using one class to make many conditions, for example one per custom field, ensure each instance returns a
     * unique value.
     *
     * Conditions must override this method. Conditions which still implement the deprecated static
     * {@see get_condition_key()} are supported until its final deprecation.
     *
     * @return string
     */
    public function get_key(): string {
        // Deprecation bridge, to be removed in the final deprecation of get_condition_key().
        // A subclass written before get_key() existed declares only the static get_condition_key(), so use that.
        // Without the is_overridden() check this would call the base get_condition_key(), which calls get_key(),
        // recursing forever for a class that implements neither.
        if (self::is_overridden('get_condition_key')) {
            debugging(
                static::class . ' should implement get_key() instead of the deprecated get_condition_key().',
                DEBUG_DEVELOPER,
            );
            return static::get_condition_key();
        }
        throw new \coding_exception(static::class . ' must implement get_key().');
    }

    /**
     * Return an SQL fragment to be ANDed into the WHERE clause to filter which questions are shown.
     *
     * @return string SQL fragment. Must use named parameters.
     */
    public function where() {
        return $this->where;
    }

    /**
     * Return parameters to be bound to the above WHERE clause fragment.
     * @return array parameter name => value.
     */
    public function params() {
        return $this->params;
    }

    /**
     * Display GUI for selecting criteria for this condition. Displayed when Show More is open.
     *
     * Compare display_options(), which displays always, whether Show More is open or not.
     * @return bool|string HTML form fragment
     * @deprecated since Moodle 4.0 MDL-72321 - please do not use this function any more.
     * @todo Final deprecation on Moodle 4.1 MDL-72572
     */
    public function display_options_adv() {
        debugging('Function display_options_adv() is deprecated, please use filtering objects', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Display GUI for selecting criteria for this condition. Displayed always, whether Show More is open or not.
     *
     * Compare display_options_adv(), which displays when Show More is open.
     * @return bool|string HTML form fragment
     * @deprecated since Moodle 4.0 MDL-72321 - please do not use this function any more.
     * @todo Final deprecation on Moodle 4.1 MDL-72572
     */
    public function display_options() {
        debugging('Function display_options() is deprecated, please use filtering objects', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Get the list of available joins for the filter.
     *
     * @return array
     */
    public function get_join_list(): array {
        return [
            datafilter::JOINTYPE_NONE,
            datafilter::JOINTYPE_ANY,
            datafilter::JOINTYPE_ALL,
        ];
    }

    /**
     * Method to be overridden in condition classes to filter out anything invalid from the filterconditions array.
     *
     * This can be applied anywhere where the $filterconditions array exists, to let condition plugins remove elements
     *  from the array, based on their own internal logic/validation. For example, this is used on the
     *  /mod/quiz/editrandom.php page to filter out question categories which no longer exist, which previously
     *  broke the editrandom page.
     *
     * @param array $filterconditions
     * @return array
     */
    public function filter_invalid_values(array $filterconditions): array {
        return $filterconditions;
    }

    /**
     * Given an array of filters, pick the entry that matches the condition key and return it.
     *
     * @deprecated since Moodle 6.0 MDL-84404
     * @see self::find_filter()
     * @param array $filters Array of filters, keyed by condition.
     * @return ?array The filter that matches this condition
     */
    #[\core\attribute\deprecated('condition::find_filter()', since: '6.0', mdl: 'MDL-84404')]
    public static function get_filter_from_list(array $filters): ?array {
        \core\deprecation::emit_deprecation_if_present([self::class, __FUNCTION__]);
        return (new static())->find_filter($filters);
    }

    /**
     * Given an array of filters, pick the entry that matches this condition's key and return it.
     *
     * @param array $filters Array of filters, keyed by condition.
     * @return ?array The filter that matches this condition
     */
    public function find_filter(array $filters): ?array {
        // Deprecation bridge, to be removed in the final deprecation of get_filter_from_list().
        // A subclass written before find_filter() existed may override the static get_filter_from_list() with its own
        // lookup, so respect that. The base get_filter_from_list() calls find_filter(), so only call it when a subclass
        // has overridden it, to avoid recursing forever.
        if (self::is_overridden('get_filter_from_list')) {
            debugging(
                static::class . ' should implement find_filter() instead of the deprecated get_filter_from_list().',
                DEBUG_DEVELOPER,
            );
            return static::get_filter_from_list($filters);
        }
        return $filters[$this->get_key()] ?? null;
    }

    /**
     * Return the SQL WHERE condition and parameters to be ANDed with other filter conditions.
     *
     * @deprecated since Moodle 6.0 MDL-84404
     * @see self::build_query()
     * @param array $filter filter properties
     * @return array ['SQL where condition', ['param1' => 'value1', 'param2' => 'value2', ...]]
     */
    #[\core\attribute\deprecated('condition::build_query()', since: '6.0', mdl: 'MDL-84404')]
    public static function build_query_from_filter(array $filter): array {
        \core\deprecation::emit_deprecation_if_present([self::class, __FUNCTION__]);
        return (new static())->build_query($filter);
    }

    /**
     * Return the SQL WHERE condition and parameters to be ANDed with other filter conditions.
     *
     * The $filter parameter recieves an array with a 'values' key, containing an array of the filter values selected,
     * and a 'jointype' key containing the selected join type.
     *
     * Conditions must override this method. Conditions which still implement the deprecated static
     * {@see build_query_from_filter()} are supported until its final deprecation.
     *
     * @param array $filter filter properties
     * @return array ['SQL where condition', ['param1' => 'value1', 'param2' => 'value2', ...]]
     */
    public function build_query(array $filter): array {
        // Deprecation bridge, to be removed in the final deprecation of build_query_from_filter().
        // A subclass written before build_query() existed declares only the static build_query_from_filter(), so use that.
        // Without the is_overridden() check this would call the base build_query_from_filter(), which calls build_query(),
        // recursing forever for a class that implements neither.
        if (self::is_overridden('build_query_from_filter')) {
            debugging(
                static::class . ' should implement build_query() instead of the deprecated build_query_from_filter().',
                DEBUG_DEVELOPER,
            );
            return static::build_query_from_filter($filter);
        }
        throw new \coding_exception(static::class . ' must implement build_query().');
    }

    /**
     * Whether the subclass declares its own version of a method, rather than inheriting this class's.
     *
     * This supports the deprecation of the static get_condition_key(), get_filter_from_list() and
     * build_query_from_filter() in favour of the instance methods get_key(), find_filter() and build_query().
     * During the deprecation, two kinds of existing code must keep working:
     * - subclasses which only implement the static methods, so the instance methods fall back to them;
     * - code which calls the static methods on converted subclasses, so the static methods call the instance methods.
     *
     * Each base method calls its counterpart, so checking whether the subclass overrides the static method tells the
     * instance method which way to go, and stops a class which implements neither from recursing forever.
     * method_exists() cannot do this, as the methods always exist on this class, and the deprecated attribute cannot,
     * as attributes are not inherited by overriding methods.
     *
     * Remove this method with the bridges that use it, in the final deprecation of the static methods.
     *
     * @param string $method The method name.
     * @return bool True if a subclass declares the method.
     */
    private static function is_overridden(string $method): bool {
        return (new \ReflectionMethod(static::class, $method))->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * Convert and map values in the restored filtercondition to corresponding values on the current site/course.
     *
     * @param array $filtercondition The origin $filtercondition with preceding conversions applied.
     * @param stdClass $setreference The set reference record from the backup.
     * @param restore_questions_activity_structure_step $restorestep The restore step.
     * @param bool $originalbankinbackup Was the original question bank these questions belonged to included in the backup?
     * @return array the modified $filtercondition
     */
    public function restore_filtercondition(
        array $filtercondition,
        stdClass $setreference,
        restore_questions_activity_structure_step $restorestep,
        bool $originalbankinbackup = false,
    ): array {
        return $filtercondition;
    }
}
