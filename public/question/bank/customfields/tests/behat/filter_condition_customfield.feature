@qbank @qbank_customfields @core_customfield @javascript
Feature: Filter questions by custom fields
  As a teacher
  In order to find questions in the question bank
  I want to filter the list of questions by their custom field values

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity | name    | intro           | course | idnumber |
      | qbank    | Qbank 1 | Question bank 1 | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | qbank1    | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name            | questiontext               |
      | Test questions   | truefalse | First question  | Answer the first question  |
      | Test questions   | truefalse | Second question | Answer the second question |
      | Test questions   | truefalse | Third question  | Answer the third question  |
    And the following "custom field categories" exist:
      | name            | component          | area     | itemid |
      | Question fields | qbank_customfields | question | 0      |
    And the following "custom fields" exist:
      | name        | category        | type     | shortname  | configdata                            |
      | Topic       | Question fields | text     | topic      |                                       |
      | Notes       | Question fields | textarea | notes      |                                       |
      | Review date | Question fields | date     | reviewdate |                                       |
      | Reviewed    | Question fields | checkbox | reviewed   |                                       |
      | Difficulty  | Question fields | select   | difficulty | {"options":"Easy\\nMedium\\nHard"}    |
      | Marks       | Question fields | number   | marks      |                                       |
      | Secret      | Question fields | text     | secret     | {"visibility":"0"}                    |
    And the following "qbank_customfields > question custom field values" exist:
      | question        | field      | value                |
      | First question  | topic      | Algebra basics       |
      | First question  | notes      | Practise fractions   |
      | First question  | reviewdate | 2024-01-05 12:00     |
      | First question  | reviewed   | 1                    |
      | First question  | difficulty | Easy                 |
      | First question  | marks      | 1                    |
      | Second question | topic      | Geometry             |
      | Second question | notes      | Practise angles      |
      | Second question | reviewdate | 2024-01-15 12:00     |
      | Second question | reviewed   | 0                    |
      | Second question | difficulty | Medium               |
      | Second question | marks      | 2                    |
      | Third question  | topic      | Advanced algebra     |
      | Third question  | reviewdate | 2024-01-25 12:00     |
      | Third question  | difficulty | Hard                 |
      | Third question  | marks      | 3                    |
    And I am on the "Qbank 1" "core_question > question bank" page logged in as "admin"
    And I apply question bank filter "Category" with value "Test questions"
    And I should see "First question"
    And I should see "Second question"
    And I should see "Third question"

  Scenario: Only visible custom fields are available as filter conditions
    Then "[data-filterregion=filtertypedata] [data-field-title='Topic']" "css_element" should exist
    And "[data-filterregion=filtertypedata] [data-field-title='Secret']" "css_element" should not exist

  Scenario: Filter by a text custom field
    When I apply question bank filter "Topic" with value "algebra"
    Then I should see "First question"
    And I should not see "Second question"
    And I should see "Third question"

  Scenario: Filter by a text area custom field
    When I apply question bank filter "Notes" with value "angles"
    Then I should not see "First question"
    And I should see "Second question"
    And I should not see "Third question"

  Scenario: Filter by a date custom field
    When I add question bank filter "Review date"
    And I set the field "Select dates" to "Between"
    And I set the field "Review date after" to "2024-01-10T00:00"
    And I set the field "Review date before" to "2024-01-20T00:00"
    And I press "Apply filters"
    Then I should not see "First question"
    And I should see "Second question"
    And I should not see "Third question"

  Scenario: Filter by a checkbox custom field
    When I apply question bank filter "Reviewed" with value "Yes"
    Then I should see "First question"
    And I should not see "Second question"
    And I should not see "Third question"
    And I set the field "Reviewed" to "No"
    And I press "Apply filters"
    And I should not see "First question"
    And I should see "Second question"
    And I should see "Third question"

  Scenario: Filter by a dropdown menu custom field
    When I apply question bank filter "Difficulty" with value "Hard"
    Then I should not see "First question"
    And I should not see "Second question"
    And I should see "Third question"

  Scenario: Filter by a number custom field
    When I add question bank filter "Marks"
    And I set the field "Marks comparison" to "Between"
    And I set the field "Marks from" to "2"
    And I set the field "Marks to" to "3"
    And I press "Apply filters"
    Then I should not see "First question"
    And I should see "Second question"
    And I should see "Third question"
    # The filter is kept when the page is reloaded.
    And I reload the page
    And the field "Marks comparison" matches value "Between"
    And the field "Marks from" matches value "2"
    And the field "Marks to" matches value "3"
    And I should not see "First question"
    And I should see "Second question"
    And I should see "Third question"
    And I set the field "Marks comparison" to "Greater than"
    And I set the field "Marks value" to "2"
    And I press "Apply filters"
    And I should not see "First question"
    And I should not see "Second question"
    And I should see "Third question"
