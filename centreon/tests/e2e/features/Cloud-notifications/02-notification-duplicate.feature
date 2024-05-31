@REQ_MON-19630
Feature: Duplicating a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user needs to duplicate a Notification Rule
  So that the user can save time and configure a new Notification Rule without starting from scratch

  Background:
    Given a user with access to the Notification Rules page
    And a Notification Rule is already created
    And the user is on the Notification Rules page

  @TEST_MON-33206
  Scenario: Duplicating a Notification Rule
    When the user selects the duplication action on a Notification Rule
    And the user enters a new Notification Rule name
    And the user confirms to duplicate
    Then a success message is displayed
    And the duplicated Notification Rule with same properties is displayed in the listing
    And the duplicated Notification Rule features the same properties as the initial Notification Rule

  @TEST_MON-33207
  Scenario: Discard duplicating a Notification Rule
    When the user selects the duplication action on a Notification Rule
    And the user clicks on the discard action
    Then the duplication action is cancelled

  @TEST_MON-33208
  Scenario: Duplicating a Notification Rule with an already existing name
    When the user selects the duplication action on a Notification Rule
    And the user enters a name that is already taken
    Then an error message is displayed indicating that the duplication is not possible
    And the duplicate button is disabled
