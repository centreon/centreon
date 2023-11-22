@ignore
@REQ_MON-24180
Feature: Duplicating a notification rule
  As a Centreon user with access to the notification rules page
  The user need to duplicate a notification rule
  So that the user can save time and configure a new one without starting from scratch

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page
    And a notification rule is already created

  Scenario: Duplicating a notification rule
    When the user selects the duplication action on a notification rule
    And the user enters a new notification rule name
    And the user confirms to duplicate
    Then a success message is displayed
    And duplicated notification rule with same properties is displayed in the listing

  Scenario: Discard duplicating a notification rule
    When the user selects the duplication action on a notification rule
    And the user clicks on the discard action
    Then the discard action is cancelled

  Scenario: Duplicating a notification rule with an already existing name
    When the user selects the duplication action on a notification rule
    And the user enters a name that is already taken and confirms
    Then an error message is displayed indicating that the duplication is not possible
