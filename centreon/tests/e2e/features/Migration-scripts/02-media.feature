@ignore
@REQ_MON-24294

Feature: Migration of medias from a source platform to a target platform

  Background:
    Given an admin user who wants to migrate medias from a source platform to a target platform
    And the user has access to both the source and target platforms
    And another non-admin user has the necessary rights to manage medias
    And the non-admin user has access to both the source and target platforms

  Scenario: Medias creation on the source platform
    Given a non-admin user logged in on the source platform
    When the user adds a new media
    Then the media is added and listed on the source platform

  Scenario: Execution of the migration script with the complete command
    Given an admin user logged in on the source platform on the terminal
    When the user runs the following command in the terminal:
    """
        php /usr/share/centreon/bin/migration media:all {target_url}
    """
    Then the user is asked for the API token of the target platform

  Scenario: Successful execution of the migration script
    Given an admin user who has entered the correct command line
    And the user is asked for the API token of the target platform
    When the user enters the correct API token
    Then the migration script is executed without errors
    And the same media as in the source platform is added and displayed in the media listing

  Scenario: Incorrect token entered
    Given an admin user who has entered the correct command line
    And the user is asked for the API token of the target platform
    When the user enters an incorrect API token
    Then the migration script is not executed
    And an error is displayed

  Scenario: Execution of the migration script without the target platform IP
    Given an admin user logged in on the terminal of the source platform
    When the user runs the following command in the terminal:
    """
        php /usr/share/centreon/bin/migration media:all
    """
    Then the migration script does not succeed
    And a "missing URL" error is displayed

  Scenario: Comparing media details between platforms
    Given a non-admin user logged in on the source and target platforms
    And the original and migrated medias are displayed
    When the user compares the medias parameters
    Then the parameters are identical on both platforms

  Scenario: Editing the media on the target platform after migration
    Given a non-admin user logged in on the target platform
    And the migrated media is displayed
    When the user updates the media
    Then the media is successfully updated

  Scenario: Deleting the media on the target platform after migration
    Given a non-admin user logged in on the target platform
    When the user deletes the media
    Then the media is deleted and no longer displayed

  Scenario: Re-execution of the migration script
    Given an admin user who successfully executed the migration script once
    When the user executes the migration script a second time
    Then the migration script succeeds
    And errors are displayed if medias with the same name already exists in the target platform
