Feature: Configuration of a command
  As a Centreon user
  I want to manipulate a command
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And the admin user is on the commands Configuration page

  @TEST_MON-158775
  Scenario: Create a new command
    When the admin user creates a command
    Then the command is displayed in the list

  @TEST_MON-158776
  Scenario: Change the properties of a command
    When the admin user changes the properties of a command
    Then the properties are updated

  @TEST_MON-158777
  Scenario: Duplicate an existing command
    When the admin user duplicates a command
    Then the new command has the same properties

  @TEST_MON-158778
  Scenario: Delete an existing command
    When the admin user deletes a command
    Then the deleted command is not displayed in the list

  @TEST_MON-158779
  Scenario Outline: Create different types of commands
    When the admin user creates a "<type>" command
    Then the "<type>" command is displayed on the listing page

    Examples:
      | type          |
      | notification  |
      | discovery     |

  @TEST_MON-158940
  Scenario: Display Host command arguments
    Given a host being configured
    When the admin user selects a check command on the host form
    Then Arguments of this command are displayed for the host
    And the admin user can configure those arguments on the host form

  @TEST_MON-158939
  Scenario: Display Service command arguments
    Given a service being configured
    When the admin user selects a check command on the service form
    Then Arguments of this command are displayed for the service
    And the admin user can configure those arguments on the service form

  Scenario: Displaying the number of services using a check command
    Given a check command being configured
    And a service is configured
    When the admin user opens the service in edit mode
    And the admin user sets the configured check command as the check command of the service
    And the admin user saves the configuration
    Then the "Used by services" column for the check command is updated accordingly

  Scenario: Displaying the number of hosts using a check command
    Given a check command is configured
    And a host is configured
    When the admin user opens the host in edit mode
    And the admin user sets the configured check command as the check command of the host
    And the admin user saves the configuration
    Then the "Used by hosts" column for the check command is updated accordingly