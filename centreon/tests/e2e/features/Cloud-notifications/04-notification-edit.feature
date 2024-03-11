@REQ_MON-19625
Feature: Editing Notification Rule configuration
  As a Centreon user with access to the Notification Rules page
  The user wants to edit a Notification Rule
  So that the user can adapt to the ever-evolving needs of the monitoring system

  Background:
    Given a user with access to the Notification Rules page
    And a Notification Rule is already created
    And the user is on the Notification Rules page

  @TEST_MON-33215
  Scenario: Editing a Notification Rule resources configuration
    When the user selects the edit action on a Notification Rule
    And the user changes the resources selection and corresponding status change parameters
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then only notifications for status changes of the updated resource parameters are sent

  @TEST_MON-33214
  Scenario: Editing a Notification Rule users configuration
    When the user selects the edit action on a Notification Rule
    And the user changes the contact configuration
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then the notifications for status changes are sent only to the updated contact

  @TEST_MON-33216
  Scenario Outline: Toggling Notification Rule status on listing
    When the user selects the '<action>' action on a Notification Rule line
    And the notification refresh delay has been reached
    Then '<prefix>' notification is sent for this rule once

    Examples:
      | action  | prefix  |
      | enable  | one     |
      | disable | no more |

  @TEST_MON-33213
  Scenario Outline: Toggling Notification Rule status on edition
    When the user selects the edit action on a Notification Rule
    And the user '<action>' the Notification Rule
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then only notifications for status changes of the updated resource parameters are sent

    Examples:
      | action  |
      | enable  |
      | disable |
