@ignore
@REQ_MON-21761

Feature: Migration of commands from a source platform to a target platform

  Background:
    Given an admin user who wants to migrate commands from a platform source to a platform target
    And the user has access to both the source and target platforms
    And another non-admin user has the necessary rights to manage commands
    And the non-admin user has access to both the source and target platforms

  Scenario: Command creation on the source platform
    Given a non-admin user logged in on the source platform
    When the user creates a new command
    Then the command is created and listed on the source platform

  Scenario: Execution of the migration script with the complete command
    Given an admin user logged in on the source platform on the terminal
    When the user runs the following command in the terminal:
    """
        php /usr/share/centreon/bin/migration command:all {target_url}
    """
    Then a command line is displayed asking for the API token of the target platform

  Scenario: Successful execution of the migration script
    Given an admin user who has entered the correct command line
    And the command line asking for the API token of the target platform is displayed
    When the user enters the correct API token
    Then the migration script is executed without errors
    And the same command as in the source platform is created and displayed in the command listing

  Scenario: Wrong token entered
    Given an admin user who has entered the correct command line
    And the command line asking for the API token of the target platform is displayed
    When the user enters a wrong API token
    Then the migration script is not executed
    And an error is displayed

  Scenario: Execution of the migration script without the target platform IP
    Given an admin user logged in the terminal of the source platform
    When the user runs the following command in the terminal :
    """
        php /usr/share/centreon/bin/migration command:all
    """
    Then the migration script is not executed
    And a "missing URL" error is displayed

  Scenario: Comparing command details between platforms
    Given a non-admin user logged in on the source and target platforms
    And the original and migrated commands are displayed
    When the user compares the commands parameters
    Then the parameters are identical on both platforms

  Scenario: Editing the command on the target platform
    Given a non-admin user logged in on the target platform
    And the migrated command is displayed
    When the user updates the command
    Then the command is successfully updated

  Scenario: Comparing monitoring information of services based on the command
    Given a non-admin user logged in on the source and target platforms
    When the user creates a service based on the command of each platform
    And exports the configuration of both platforms
    And runs a check on each platform
    Then the services must have the same output on both platforms

  Scenario: Validating monitoring graph on the target platform
    Given a non-admin user logged in on the target platform
    And a monitored service based on the command
    When the service has run some checks
    Then a graph is created with the service data
    And its information is correct

  Scenario: Deleting the command on the target platform
    Given a non-admin user logged in on the target platform
    When the user deletes the command
    Then the command is deleted and no longer displayed

  Scenario: Re-execution of the migration script
    Given an admin user who successfully executed the migration script once
    When the user executes the migration script a second time
    Then the migration script is executed
    And errors are displayed when trying to migrate commands with already existing names in the target platform