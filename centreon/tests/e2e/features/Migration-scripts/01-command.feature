@ignore
@REQ_MON-24871

Feature: Migration of commands from a source platform to a target platform

Background:
    Given a user who wants to migrate commands from a platform source to a platform target
    And the user has access to both the source and target platforms
    And the user has the necessary rights to manage commands

Scenario: Command creation on the source platform
    Given a user logged in the source platform
    When the user create a new command
    Then the command is created 

Scenario: Execution of the migration script with the complete command
    Given a root user logged in the terminal of the source platform
    When the user run the following command in the terminal : 
    """
        php /usr/share/centreon/bin/migration command:all {target_url}
    """
    Then a command line is displayed asking for the API token of the target platform 

Scenario: Successful execution of the migration script
    Given a root user who has enters the correct command line 
    And a new command line displayed asking for the API token of the target platform
    When the user enters the correct API token 
    Then the migration script is executed without errors

Scenario: Wrong token entered
    Given a root user who has enters the correct command line 
    And a new command line displayed asking for the API token of the target platform
    When the user enters a wrong API token 
    Then the migration script is not executed 
    And an error is displayed

Scenario: Execution of the migration script without the target platform IP
    Given a root user logged in the terminal of the source platform
    When the user run the following command in the terminal : 
    """
        php /usr/share/centreon/bin/migration command:all
    """
    Then the migration script is not executed 
    And an error is displayed for missing url

Scenario: Validating presence of migrated command on the target platform
    Given the user is logged in to the target platform
    And the migration script runs successfully
    When the user go to the command listing
    Then the same command than in the source platform is created and displayed

Scenario: Comparing command details between platforms
    Given the user is logged in to the source and target platforms
    And the original and migrated commands are displayed
    When the user compares the commands parameters
    Then the parameters are identical on both platforms

Scenario: Editing the command on the target platform
    Given the user is logged in to the target platform
    And the migrated command is displayed
    When the user updates the command
    Then the command is successfully updated 

Scenario: Examining monitoring information of associated services
    Given an examination of the monitoring information of associated services
    When checking metrics and performance data
    Then confirm the similarity of details on both platforms
