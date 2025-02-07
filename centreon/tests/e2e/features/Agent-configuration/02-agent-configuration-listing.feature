@REQ_MON-146653
Feature: Access an Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To list the agent configuration

  @TEST_MON-151995
  Scenario: Access to Agents Configuration page
    Given a non-admin user is logged in
    When the user clicks on the Agents Configuration page
    Then the user sees the Agents Configuration page

  @TEST_MON-151996
  Scenario: List all information of an agent configuration
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration
    When the user clicks on the line of the agent configuration
    Then a pop up is displayed with all of the agent information

  @TEST_MON-153922
  Scenario: Searching for a name of a PAC that doesn't exist
    Given a non-admin user is in the Agents Configuration page
    And some poller agent configurations are created
    When the user enters a non-existent name into the search bar
    Then an empty listing page with no results is displayed

  @TEST_MON-153923
  Scenario: Searching for a name of a PAC that exist
    Given a non-admin user is in the Agents Configuration page
    And some configured poller agent configurations
    When the user enters an existing name into the search bar
    Then a listing page is displayed showing only the poller agent configurations that match the entered name