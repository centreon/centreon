Feature: Creating a Notification Rule
  As a Centreon admin user,
  I want to verify that the backup settings are properly configured
  So that I can ensure that all necessary backup options are enabled and correctly set.

  @TEST_MON-147112
  Scenario: Verify Backup Configuration in the UI
    Given an admin user is logged in
    When the admin user acces to the backup page
    Then backup is enable in UI
    And backup directory is set
    And backup temporary is set
    And database backup options is set
    And Mysql configuration file path is set

  @TEST_MON-147112
  Scenario: Run the full backup
    Given an admin user is logged in
    When the admin user acces to the backup page
    And the admin user enables backup for all configuration files
    And the admin user selects full backup day
    And the admin user saves the backup configuration and export the configuration
    And after the scheduled cron job has run
    Then the database backups and configuration files should be present in the backup directory
