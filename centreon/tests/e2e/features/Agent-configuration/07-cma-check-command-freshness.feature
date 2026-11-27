Feature: Freshness of the objects checked by the Centreon Monitoring Agent
  As a Centreon administrator
  I want the objects using a Centreon Monitoring Agent check command to have their freshness checked
  So that a result the agent stops pushing is detected

  @MON-215220
  Scenario Outline: Freshness is enabled at 500 seconds on a new <object> using a Centreon Monitoring Agent check command
    Given an admin user is logged in a Centreon server
    And the Centreon Monitoring Agent connector is installed
    Given the admin is on the <object> creation form
    When the admin creates a <object> with a Centreon Monitoring Agent check command
    And the admin opens the <object> again
    Then freshness is enabled
    And the freshness threshold is 500 seconds

    Examples:
      | object           |
      | host template    |
      | host             |
      | service template |
      | service          |
