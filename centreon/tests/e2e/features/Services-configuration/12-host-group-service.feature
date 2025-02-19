Feature: Host Group Service configuration
  As a Centreon user
  I want to manipulate a host group service
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server

  @TEST_MON-158047
  Scenario: Create a new host group service
    Given some service groups are configured
    And some service categories are configured
    When the user goes to Configuration > Services > Services by host group
    And the user Add a new host group service
    Then the host group service is added to the listing page

  @TEST_MON-158048
  Scenario: Change the properties of one existing host group service
    Given a host group service is configured
    When the user changes the properties of the host group service
    Then the properties are updated

  @TEST_MON-158049
  Scenario: Duplicate one existing host group server
    Given a host group service is configured
    When the user duplicates the host group service
    Then the new duplicated host group service has the same properties

  @TEST_MON-158050
  Scenario: Delete one existing host group service
    Given a host group service is configured
    When the user deletes the host group service
    Then the deleted host group service is not displayed in the list