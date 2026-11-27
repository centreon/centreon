Feature: Database partitioning and purge crons
  As a Centreon administrator
  I want the nightly crons to provision new partitions and drop the expired ones
  So that the monitoring database keeps accepting data without growing forever

  # The CI stack lives a few minutes: "since at least one hour" is checked as
  # "the central monitors its resources" (hosts checked, logs written).
  @MON-214376 @system
  Scenario: The nightly partitioning and purge crons keep partitions ahead and drop the partitions past their retention
    Given a central monitoring resources since at least one hour
    And the Forward provisioning set to 12 days and the Retention duration for logs set to 31 days in Administration > Parameters > Options
    And an old partition p20250101 holding the log line rbt-purge-old added at the start of the logs table
    When the partitioning command of /etc/cron.d/centstorage runs as its cron user
    Then data_bin, logs, log_archive_host and log_archive_service each have a partition dated today plus 12 days and an empty pmax partition
    When the purge command of /etc/cron.d/centstorage runs as its cron user
    Then the partition p20250101 and the line rbt-purge-old are gone from the logs table
    And no partition of the logs table is older than the 31 days of retention
    And both cron logs end without error
