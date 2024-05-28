@core @core_question
Feature: A teacher can move questions between categories in the question bank
  In order to organize my questions
  As a teacher
  I move questions between categories

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | weeks |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity   | name    | intro              | course | idnumber |
      | qbank      | Qbank 1 | Question bank 1    | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | questioncategory    | name                |
      | Activity module | qbank1    | Top                 | top                 |
      | Activity module | qbank1    | top                 | Default for Qbank 1 |
      | Activity module | qbank1    | Default for Qbank 1 | Subcategory         |
      | Activity module | qbank1    | top                 | Used category       |
    And the following "questions" exist:
      | questioncategory | qtype | name                      | questiontext                  |
      | Used category    | essay | Test question to be moved | Write about whatever you want |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  @javascript
  Scenario: Move a question between categories via the question page
    When I am on the "Qbank 1" "core_question > question bank" page logged in as "teacher1"
    And I apply question bank filter "Category" with value "Used category"
    And I click on "Test question to be moved" "checkbox" in the "Test question to be moved" "table_row"
    And I click on "With selected" "button"
    And I click on question bulk action "move"
    And I set the field "Question category" to "Subcategory"
    And I press "Move to"
    Then I should see "Test question to be moved"
    And I should see "Subcategory (1)" in the ".form-autocomplete-selection" "css_element"
