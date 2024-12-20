Feature: Massive Change on services
  As a Centreon administrator
  I want to modify some properties of similar services
  To configure quickly numerous services at the same time

  Background:
    Given an admin user is logged in a Centreon server
    And several services have been created with mandatory properties

  @TEST_MON-151902
  Scenario: Configure by massive change several services with same properties
    When the user has applied "Mass Change" operation on several services
    Then all selected services are updated with the same values