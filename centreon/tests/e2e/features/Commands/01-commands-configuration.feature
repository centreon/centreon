Feature: Configuration of a command
  As a Centreon user
  I want to manipulate a command
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-158775
  Scenario: Create a new command
    When the user creates a command
    Then the command is displayed in the list

  @TEST_MON-158776
  Scenario: Change the properties of a command
    When the user changes the properties of a command
    Then the properties are updated

  @TEST_MON-158777
  Scenario: Duplicate an existing command
    When the user duplicates a command
    Then the new command has the same properties

  @TEST_MON-158778
  Scenario: Delete an existing command
    When the user deletes a command
    Then the deleted command is not displayed in the list

  @TEST_MON-158779
  Scenario Outline: Create different types of commands
    When the user creates a "<type>" command
    Then the command is displayed on the "<type>" page

    Examples:
      | type          |
      | check         |
      | notification  |
      | discovery     |
      | miscellaneous |

  @TEST_MON-158940
  Scenario: Display Host command arguments
    Given a host being configured
    When the user selects a check command on the host form
    Then Arguments of this command are displayed for the host
    And the user can configure those arguments on the host form

  @TEST_MON-158939
  Scenario: Display Service command arguments
    Given a service being configured
    When the user selects a check command on the service form
    Then Arguments of this command are displayed for the service
    And the user can configure those arguments on the service form