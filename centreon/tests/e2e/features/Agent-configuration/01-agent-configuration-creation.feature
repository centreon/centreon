@REQ_MON-146653
Feature: Create a new Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To manage the Agent Configurations

  @TEST_MON-151989
  Scenario: Add an agent with all informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in all the informations
    And the user clicks on Create
    Then the first agent is displayed in the Agents Configuration page

  @TEST_MON-151990
  Scenario: Add an agent with mandatory informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in the mandatory informations
    And the user clicks on Create
    Then the second agent is displayed in the Agents Configuration page

  @TEST_MON-151991
  Scenario: Add a centreon agent with multiple hosts
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user selects the centreon agent
    And the user clicks to add a second host
    Then a second group of parameters for hosts is displayed
    When the user fills in the centreon agent parameters
    And the user clicks on Create
    Then the third agent is displayed in the Agents Configuration page

  @TEST_MON-151992
  Scenario: Add an agent with missing informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in all the mandatory informations
    Then the user cannot click on Create

  @TEST_MON-151993
  Scenario: Add an agent with incorrect informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in correct type of informations
    Then the form displayed an error

  @TEST_MON-151994
  Scenario: Cancel a creation form
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in the needed informations
    And the user clicks on the Cancel button of the creation form
    Then a pop-up appears to confirm cancellation
    And the user confirms the the cancellation
    Then the creation form is closed
    And the agent has not been created
    When the user clicks on Add
    Then the form fields are empty