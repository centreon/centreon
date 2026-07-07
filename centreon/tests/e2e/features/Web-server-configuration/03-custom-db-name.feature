Feature: Central poller configuration generation with non-standard database names
  As a Centreon administrator whose databases are not named centreon / centreon_storage
  I want the central poller configuration to be generated successfully
  So that my configuration can be deployed regardless of the physical database names

  @MON-204575
  Scenario: Generate central poller configuration with non-standard database names
    Given a platform whose databases are not named centreon or centreon_storage
    When the administrator exports the central poller configuration
    Then the configuration is generated and reloaded successfully
