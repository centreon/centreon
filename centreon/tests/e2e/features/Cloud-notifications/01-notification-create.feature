@ignore
@REQ_MON-24178
Feature: Creating a notification rule
  As a Centreon user with access to the notification rules page
  I need to create a notification rule
  So that configured resources status changes are notified to the configured users

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page

  Scenario: Creating a notification rule
    When the user defines a name for the rule
    And the user selects some resources and associated events on which to notify

    And the user selects some contacts and/or contact groups
    And the user defines a mail subject and body
    And the user clicks on the "Save" button and confirm
    Then a success message is displayed and the created notification rule is displayed in the listing

    When changes occur in the configured statuses for the selected resources
    And the hard state is reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured contacts and contact groups with the configured format