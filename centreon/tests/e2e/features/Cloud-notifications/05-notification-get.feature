@ignore
@REQ_MON-24183
Feature: List Notification Rules
  As a Centreon user with access to the Notification Rules page
  I want to view the list of notification rules
  So that I can effectively organize my alerts in the monitoring system

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page
    And there is at least one notification rule
    And pagination is set to 10 items per page

  Scenario Outline: Listing Notification Rules with Pagination
    When I am on listing page <page>
    And the result shows <count> items
    Then I see a previous page link <previous_page> and next page link <next_page>
    Examples:
      | count | page | previous_page | next_page |
      | 1     | 1    | disabled      | disabled  |
      | 15    | 1    | 2             | 1         |
      | 15    | 2    | 1             | disabled  |
      | 21    | 1    | disabled      | 2         |
      | 21    | 2    | 1             | 3         |
      | 21    | 3    | 2             | disabled  |