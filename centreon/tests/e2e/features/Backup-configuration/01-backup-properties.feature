Feature: Creating a Notification Rule
  As a Centreon admin user,
  I want to verify that the backup settings are properly configured
  So that I can ensure that all necessary backup options are enabled and correctly set.

  @TEST_MON-147112
  Scenario: Verify Backup Configuration in the UI
    Given an admin user is logged in
    When the admin user accesses the backup page
    Then the backup is enabled in the UI
    And the backup directory is set
    And the backup temporary directory is set
    And the database backup options are set
    And the MySQL configuration file path is set

  @TEST_MON-147112
  Scenario: Run the full backup
    Given an admin user is logged in
    When the admin user accesses the backup page
    And the admin user enables backup for all configuration files
    And the admin user selects the "full backup day" option
    And the admin user saves the backup configuration and exports the configuration
    And after the scheduled cron job has run
    Then the database backups and configuration files are present in the backup directory
