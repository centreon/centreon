@REQ_MON-16477 @system
Feature: Creating a Notification Rule
  As a Centreon user with access to the Notification Rules page
  The user needs to create a Notification Rule
  So that the configured users are notified of status changes on configured resources

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page

  @TEST_MON-38164
  Scenario Outline: Creating a <resource_type> Notification Rule for <contact_settings>
    Given a '<resource_type>' with hosts and '<contact_settings>'
    When the user defines a name for the rule
    And the user selects a '<resource_type>' with associated events on which to notify
    And the user selects the '<contact_settings>'
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected '<resource_type>'
    And the hard state has been reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured '<contact_settings>' with the configured format
    Examples:
      | contact_settings | resource_type                           |
      | one contact      | host group                              |
      | two contacts     | host group and services for these hosts |

  @TEST_MON-33204
  Scenario Outline: Creating a large volume Notification Rule for <contact_settings>
    Given a minimum of 1000 services linked to a host group and '<contact_settings>'
    When the user defines a name for the rule
    And the user selects a host group with its linked services and with associated events on which to notify
    And the user selects the '<contact_settings>'
    And the user defines a mail subject
    And the user defines a mail body
    And the user clicks on the "Save" button to confirm
    Then a success message is displayed and the created Notification Rule is displayed in the listing
    When changes occur in the configured statuses for the selected host group
    And the hard state has been reached
    And the notification refresh_delay has been reached
    Then an email is sent to the configured '<contact_settings>' with the configured format
    Examples:
      | contact_settings |
      | one contact      |
      | two contacts     |
