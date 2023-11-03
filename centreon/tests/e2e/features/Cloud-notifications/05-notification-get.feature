@ignore
@REQ_MON-24183
Feature: Get notification rules
  As a Centreon user with access to the notification rules page
  I need to show this list of notification rules

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page
    And a minimal one of notification rule is created
    And pagination is defined to 10 items

  Scenario: Getting notification rules
    When the user arrived on the listing notification rules page
    Then a minimal one line from the listing displays notification rule

  Scenario Outline: Getting notifications rules with pagination
    When the user arrived on the listing notification rules page
    And the result items is <count>
    And I am on the actually page <page>
    Then I should see a previous page link <previous_page> and next page link <next_page>

    Examples:
      | count | page | previous_page | next_page |
      | 1     | 1    | disabled      | disabled  |
      | 15    | 1    | 2             | 1         |
      | 15    | 2    | 1             | disabled  |
      | 21    | 1    | disabled      | 2         |
      | 21    | 2    | 1             | 3         |
      | 21    | 3    | 2             | disabled  |
