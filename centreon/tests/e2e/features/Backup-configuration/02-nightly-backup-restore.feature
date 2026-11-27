Feature: Nightly backup restore
  As a Centreon administrator
  I want the nightly backup to produce complete dumps and archives
  So that the platform can be restored from them

  @MON-214341 @system
  Scenario: The nightly backup produces complete dumps that restore into empty databases
    Given a central whose backup is enabled for today with the Dump type and the configuration files
    And the host rbt-backup-host exists in the configuration
    When the backup script of the nightly cron runs
    Then the backup directory holds today's centreon and centreon_storage dumps and the central and centreon-engine archives
    And each dump is a complete gzip file ending with the dump completion line
    And the centreon dump restores into an empty database with the same hosts as the platform
    And the central archive contains the Centreon configuration files
