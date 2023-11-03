@ignore
@REQ_MON-24182
Feature: Editing notification rule configuration
  As a Centreon user with access to the notification rules page
  I need to edit a notification rule
  So that I can adapt to to the ever evolving needs of my monitoring system

  Background:
    Given a user with access to the notification rules page
    And a notification rule is already created
    And the user is on the notification rules page

  Scenario: Editing a notification rule resources configuration
    When the user selects the edition action on a notification rule
    And the user changes the resources selection and corresponding status changes parameters
    And the user saves and confirm the changes
    Then only notifications for status changes of the updated resource parameters are sent once the notification refresh_delay has been reached

  Scenario Outline: Editing a notification rule users configuration
    When the user selects the edition action on a notification rule
    And the user changes the <user_type> configuration
    And the user saves and confirm the changes
    Then notifications for status changes are sent only to the updated <user_type> once the notification refresh_delay has been reached
    Examples:
      | user_type      |
      | contact        |
      | contact groups |

  Scenario Outline: Manage notification rule status on listing
    When the user selects the <action> action on a notification rule line
    Then <not_send> notification is sent for this rule once the notification refresh_delay has been reached
    Examples:
      | action   | not_send |
      | enables  | no more  |
      | disables |          |

  Scenario Outline: Manage notification rule status on edition
    When the user selects the edition action on a notification rule
    And the user <action> the notification rule
    And the user saves and confirm the changes
    Then <not_send> notification is sent for this rule once the notification refresh_delay has been reached
    Examples:
      | action   | not_send |
      | enables  | no more  |
      | disables |          |