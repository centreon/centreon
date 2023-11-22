@ignore
@REQ_MON-24182
Feature: Editing notification rule configuration
  As a Centreon user with access to the notification rules page
  The user want to edit a notification rule
  So that the user can adapt to the ever-evolving needs of my monitoring system

  Background:
    Given a user with access to the notification rules page
    And a notification rule is already created
    And the user is on the notification rules page

  Scenario: Editing a notification rule resources configuration
    When the user selects the edit action on a notification rule
    And the user changes the resources selection and corresponding status change parameters
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then only notifications for status changes of the updated resource parameters are sent

  Scenario Outline: Editing a notification rule users configuration
    When the user selects the edit action on a notification rule
    And the user changes the <user_type> configuration
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then the notifications for status changes are sent only to the updated <user_type>

    Examples:
      | user_type      |
      | contact        |
      | contact groups |

  Scenario Outline: Toggling notification rule status on listing
    When the user selects the <action> action on a notification rule line
    And the notification refresh delay has been reached
    Then <prefix> notification is sent for this rule once

    Examples:
      | action  | prefix  |
      | enable  | no more |
      | disable | one     |

  Scenario Outline: Toggling notification rule status on edition
    When the user selects the edit action on a notification rule
    And the user <action> the notification rule
    And the user saves to confirm the changes
    And the notification refresh delay has been reached
    Then only notifications for status changes of the updated resource parameters are sent

    Examples:
      | action  |
      | enable  |
      | disable |