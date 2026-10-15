Feature: Massive Change on Hosts
  As a Centreon administrator
  I want to modify some properties of similar hosts
  To configure quickly numerous hosts at the same time

  Background:
    Given an admin user is logged in a Centreon server
    And several hosts have been created with mandatory properties

  @MON-151876
  Scenario: Configure by massive change several hosts with same properties
    When the user has applied "Mass Change" operation on several hosts
    Then all the selected hosts are updated with the same values

  @MON-163479
  Scenario Outline: Change by massive change the host template of several hosts
    Given the selected hosts use a host template whose services are deployed
    When the user applies a "Mass Change" with another host template in "<mode>" mode
    Then the services of the previous host template are <previous_services> on the selected hosts
    And the services of the new host template are deployed on the selected hosts

    Examples:
      | mode        | previous_services |
      | Replacement | removed           |
      | Incremental | kept              |
