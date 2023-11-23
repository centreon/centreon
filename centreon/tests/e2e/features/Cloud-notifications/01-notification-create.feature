@ignore
@REQ_MON-16477
Feature: Creating a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user need to create a Notification Rule
  So that configured resources status changes are notified to the configured users

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page

  Scenario: Creating a Notification Rule
    When the user defines a name for the rule
    And the user selects some resources with associated events on which to notify
    And the user selects some contacts and/or contact groups
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected resources
    And the hard state is reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured contacts and/or contact groups with the configured format