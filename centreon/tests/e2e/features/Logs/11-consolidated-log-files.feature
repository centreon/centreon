Feature: Consolidated log files
  As a Centreon administrator
  I want the legacy pages and the new kernel to write their logs in the same channel files with the same format
  So that I can follow and correlate the activity of the platform in one place without exposing sensitive values

  @MON-215159 @system
  Scenario: Legacy and new-kernel actions are written to the consolidated log files
    # Reading the server log files is an administrator task, so only the super admin persona is used
    Given an administrator is logged in
    And the log directory of the central is empty of previous entries
    Given the administrator has opened a legacy configuration page
    And the administrator has opened a page served by the new kernel
    When a user fails to log in with a wrong password
    Then the log files are located in the Centreon log directory with the environment and channel in their names
    # login.log is still written on purpose: it mirrors the login events for the tools such as fail2ban
    And no legacy error log file nor new-kernel log subdirectory is written
    And the legacy and new-kernel entries share the same line format and RFC3339 timestamps
    And the failed login is logged as a warning in the access log
    And each entry carries a correlation id
    And no sensitive value appears in clear text in the log entries
