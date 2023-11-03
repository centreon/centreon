@ignore
@REQ_MON-24181
Feature: Deleting a notification rule
  As a Centreon user with access to the notification rules page
  I need to delete a notification rule
  So that configured users are no longer notified for the associated resources status changes

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page
    And a notification rule is already created

  Scenario: Deleting a notification rule
    When the user selects the delete action on a notification rule
    And the user confirms
    Then a success message is displayed and the notification rule is deleted from the listing
    And the configured users are no longer notified about the associated resources status changes once the notification refresh_delay has been reached

  Scenario: Discard deleting a notification rule
    When the user selects the delete action on a notification rule
    And the user clicks on the discard action
    Then the action is cancelled
    And return on the listing