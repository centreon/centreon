@ignore
@REQ_MON-24180
Feature: Duplicating a notification rule
  As a Centreon user with access to the notification rules page
  I need to duplicate a notification rule
  So that I can save time and configure a new one without starting from scratch

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page
    And a notification rule is already created

  Scenario: Duplicating a notification rule
    When the user selects the duplication action on a notification rule
    And the user enters a new name
    And the dupplicate action is enabled and user confirms
    Then a success message is displayed
    And then duplicated notification rule is displayed in the listing

  Scenario: Discard duplicating a notification rule
    When the user selects the duplication action on a notification rule
    And the user clicks on the discard action
    Then the action is cancelled
    And return on the listing

  Scenario: Duplicating a notification rule with an already existing name
    When the user selects the duplication action on a notification rule
    And the user enters a name that is already taken and confirms
    Then an error message is displayed and no duplicate is not possible
