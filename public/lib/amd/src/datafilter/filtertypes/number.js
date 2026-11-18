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
 * Filter type for comparing a numeric value.
 *
 * Displays a mode selector (equal to, less than, greater than or between) and one or two number inputs.
 * The filter values are the raw input values, and the selected mode is passed as the "mode" filter option.
 *
 * @module     core/datafilter/filtertypes/number
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Filter from 'core/datafilter/filtertype';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';

/**
 * The available comparison modes.
 *
 * @type {Object}
 */
export const MODES = {
    equal: 'equal',
    lessthan: 'lessthan',
    greaterthan: 'greaterthan',
    between: 'between',
};

export default class extends Filter {

    /**
     * Constructor for a new number filter.
     *
     * @param {String} filterType The type of filter that this relates to
     * @param {HTMLElement} rootNode The root node for the filterset
     * @param {Array} initialValues The initial values for the filter
     * @param {Object} filterOptions The initial filter options, containing the selected mode
     */
    constructor(filterType, rootNode, initialValues, filterOptions) {
        super(filterType, rootNode, initialValues);
        this.addModeSelector(filterOptions?.mode ?? MODES.equal).catch(Notification.exception);
    }

    /**
     * Get the selector for the mode select element of this filter.
     *
     * @returns {String}
     */
    get modeSelector() {
        return `[data-filterfield="${this.filterType}-mode"]`;
    }

    /**
     * Get the title of the field being filtered.
     *
     * @returns {String}
     */
    get title() {
        return this.getSourceDataForFilter().getAttribute('data-field-title');
    }

    /**
     * Add the value inputs to the filter row.
     *
     * @param {Array} initialValues The initial values for the inputs.
     * @returns {Promise}
     */
    async addValueSelector(initialValues = []) {
        const sourceDataNode = this.getSourceDataForFilter();
        const title = sourceDataNode.getAttribute('data-field-title');
        const labels = await getStrings([
            {key: 'numberfiltervalue', component: 'core', param: {title}},
            {key: 'numberfilterfrom', component: 'core', param: {title}},
            {key: 'numberfilterto', component: 'core', param: {title}},
        ]);
        const context = {
            filtertype: this.filterType,
            required: sourceDataNode.dataset.required,
            valuelabel: labels[0],
            fromlabel: labels[1],
            tolabel: labels[2],
            value1: initialValues[0] ?? '',
            value2: initialValues[1] ?? '',
        };
        const {html, js} = await Templates.renderForPromise('core/datafilter/filtertypes/number_selector', context);
        Templates.replaceNodeContents(this.getFilterValueNode(), html, js);
        this.updateFieldVisibility();
    }

    /**
     * Add the mode selector to the filter row.
     *
     * @param {String} mode The initially selected mode.
     * @returns {Promise}
     */
    async addModeSelector(mode) {
        const title = this.title;
        const modeStrings = await getStrings([
            {key: 'numberfiltermode', component: 'core', param: {title}},
            {key: 'filterisequalto', component: 'reportbuilder'},
            {key: 'filterlessthan', component: 'reportbuilder'},
            {key: 'filtergreaterthan', component: 'reportbuilder'},
            {key: 'between', component: 'core'},
        ]);
        const context = {
            label: modeStrings[0],
            filtertype: this.filterType,
            modeoptions: [MODES.equal, MODES.lessthan, MODES.greaterthan, MODES.between].map((value, index) => ({
                value,
                label: modeStrings[index + 1],
                selected: mode === value ? 'selected' : '',
            })),
        };
        const {html} = await Templates.renderForPromise('core/datafilter/filtertypes/datetime_mode', context);
        this.getFilterValueNode().insertAdjacentHTML('beforebegin', html);
        this.filterRoot.querySelector(this.modeSelector).addEventListener('change', () => this.updateFieldVisibility());
        this.updateFieldVisibility();
    }

    /**
     * Show the second input only when comparing between two values, and label the first input to match.
     *
     * The mode selector and the value inputs are rendered independently, so do nothing until both are present.
     */
    updateFieldVisibility() {
        const filterRoot = this.filterRoot;
        if (!filterRoot) {
            return;
        }
        const modeSelect = filterRoot.querySelector(this.modeSelector);
        const valueNode = this.getFilterValueNode();
        const firstInput = valueNode?.querySelector('[data-region="number-value1"] input');
        if (!modeSelect || !firstInput) {
            return;
        }
        const between = modeSelect.value === MODES.between;
        valueNode.querySelectorAll('[data-region="number-betweenonly"]').forEach((element) => {
            element.classList.toggle('d-none', !between);
        });
        valueNode.querySelector('[data-region="number-value2"] input').required = between;
        const firstLabel = valueNode.querySelector('[data-region="number-value1"] label');
        firstLabel.textContent = between ? firstLabel.dataset.labelBetween : firstLabel.dataset.labelSingle;
    }

    /**
     * Get the input elements for the first and second value.
     *
     * @returns {HTMLInputElement[]}
     */
    get inputs() {
        const valueNode = this.getFilterValueNode();
        return [
            valueNode.querySelector('[data-region="number-value1"] input'),
            valueNode.querySelector('[data-region="number-value2"] input'),
        ];
    }

    /**
     * Get the raw values of the inputs.
     *
     * The second value is only used when comparing between two values.
     *
     * @returns {String[]}
     */
    get values() {
        return this.inputs.map((input) => input.value);
    }

    /**
     * Get the selected mode as a filter option.
     *
     * @returns {Array} of {name:, value:} objects
     */
    get filterOptions() {
        return [
            {name: 'mode', value: this.filterRoot.querySelector(this.modeSelector).value},
        ];
    }

    /**
     * Check that the required values are entered, and that a range is not reversed.
     *
     * @returns {Boolean}
     */
    validate() {
        const [first, second] = this.inputs;
        first.setCustomValidity('');
        second.setCustomValidity('');
        if (!first.reportValidity() || !second.reportValidity()) {
            return false;
        }
        const mode = this.filterRoot.querySelector(this.modeSelector).value;
        if (mode === MODES.between && parseFloat(first.value) > parseFloat(second.value)) {
            getString('invalidnumberbetween', 'error', {min: first.value, max: second.value})
                .then((message) => {
                    second.setCustomValidity(message);
                    second.reportValidity();
                    return message;
                })
                .catch(Notification.exception);
            return false;
        }
        return true;
    }
}
