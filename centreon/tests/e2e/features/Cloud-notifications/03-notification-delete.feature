@REQ_MON-19626
Feature: Deleting a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user needs to delete a Notification Rule
  So that configured users are no longer notified status changes for the associated resources

  Background:
    Given a user with access to the Notification Rules page
    And a Notification Rule is already created
    And the user is on the Notification Rules page

  @TEST_MON-33211
  Scenario: Deleting a Notification Rule
    When the user selects the delete action on a Notification Rule
    And the user confirms the deletion
    Then a success message is displayed and the Notification Rule is deleted from the listing
    And the configured users are no longer notified of status changes for the associated resources once the notification refresh delay has been reached

  @TEST_MON-33210
  Scenario: Discard deleting a Notification Rule
    When the user selects the delete action on a Notification Rule
    And the user clicks on the discard action
    Then the deletion is cancelled
