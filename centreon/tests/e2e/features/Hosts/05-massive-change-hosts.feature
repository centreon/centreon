Feature: Massive Change on Hosts
  As a Centreon administrator
  I want to modify some properties of similar hosts
  To configure quickly numerous hosts at the same time

  Background:
    Given an admin user is logged in a Centreon server
    And several hosts have been created with mandatory properties

  @TEST_MON-151876
  Scenario: Configure by massive change several hosts with same properties
    When the user has applied "Mass Change" operation on several hosts
    Then all the selected hosts are updated with the same values