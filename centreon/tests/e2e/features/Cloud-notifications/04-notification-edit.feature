@ignore
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


  Scenario: Editing a notification rule users configuration
    When the user selects the edition action on a notification rule
    And the user changes the contact and contact groups configuration
    And the user saves and confirm the changes
    Then notifications for status changes are sent only to the updated contact and contact groups once the notification refresh_delay has been reached


  Scenario: Toggling a notification rule status
    When the user selects the edition action on a notification rule
    And the user disables the notification rule
    And the user saves and confirm the changes
    Then no more notification is sent for this rule once the notification refresh_delay has been reached

    When the user selects the edition action the disabled notification rule
    And the user enables the notification rule
    And the user saves and confirm the changes
    When changes occur in the configured statuses for the selected resources
    Then an email is sent to the configured contacts and contact groups with the configured format once the notification refresh_delay has been reached
