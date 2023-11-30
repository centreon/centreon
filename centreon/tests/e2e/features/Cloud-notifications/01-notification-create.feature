@ignore
@REQ_MON-16477
Feature: Creating a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user needs to create a Notification Rule
  So that the configured users are notified of status changes on configured resources

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page

  Scenario: Creating a host group Notification Rule
    Given a host group with hosts
    When the user defines a name for the rule
    And the user selects a host group and hosts with associated events on which to notify
    And the user selects some contacts and/or contact groups
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected host group
    And the hard state has been reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured contacts and/or contact groups with the configured format

  Scenario: Creating a service group Notification Rule
    Given a service group
    When the user defines a name for the rule
    And the user selects a service group with associated events on which to notify
    And the user selects some contacts and/or contact groups
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected service group
    And the hard state has been reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured contacts and/or contact groups with the configured format

  Scenario: Creating a business view Notification Rule
    Given a business view
    When the user defines a name for the rule
    And the user selects a business view with associated events on which to notify
    And the user selects some contacts and/or contact groups
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected business view
    And the hard state has been reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured contacts and/or contact groups with the configured format
