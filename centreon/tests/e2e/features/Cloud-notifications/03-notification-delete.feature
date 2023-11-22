@ignore
@REQ_MON-24181
Feature: Deleting a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user need to delete a Notification Rule
  So that configured users are no longer notified for the associated resources status changes

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page
    And a Notification Rule is already created

  Scenario: Deleting a Notification Rule
    When the user selects the delete action on a Notification Rule
    And the user confirms the deletion
    Then a success message is displayed and the Notification Rule is deleted from the listing
    And the configured users are no longer notified about the associated resources status changes once the notification refresh delay has been reached

  Scenario: Discard deleting a Notification Rule
    When the user selects the delete action on a Notification Rule
    And the user clicks on the discard action
    Then the deletion is cancelled
