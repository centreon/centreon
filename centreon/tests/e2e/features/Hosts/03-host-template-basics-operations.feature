Feature: HostTemplateBasicsOperations
  As a Centreon admin
  I want to manipulate a host template
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a host template is configured

  @TEST_MON-151104
  Scenario: Edit of a host template properties
    When the user changes the properties of the configured host template
    Then the properties are updated
  
  @TEST_MON-151105
  Scenario: Duplication of a host template
    When the user duplicates the configured host template
    Then a new host template is created with identical properties

  @TEST_MON-151109
  Scenario: Deletion of a host template
    When the user deletes the configured host template
    Then the deleted host template is not visible anymore on the host group page