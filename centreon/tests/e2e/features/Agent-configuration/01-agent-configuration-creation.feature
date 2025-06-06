@REQ_MON-146653
Feature: Create a new Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To manage the Agent Configurations

  @TEST_MON-151989
  Scenario: Add an agent with all information
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in all the information
    And the user clicks on Create
    Then the first agent is displayed in the Agents Configuration page

  @TEST_MON-151990
  Scenario: Add an agent with mandatory information
    Given a CMA Token is configured
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user selects the centreon agent
    Then the connection initiated by poller field must be disabled
    When the user enables the connection initiated by the poller option
    Then a new parameters group is displayed for the host
    When the user disables the connection initiated by poller option
    Then the group of parameters for the host disappears
    When the user fills in the mandatory information
    And the user clicks on Create
    Then the second agent is displayed in the Agents Configuration page

  @TEST_MON-151991
  Scenario: Add a centreon agent with multiple hosts
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user selects the centreon agent
    And the user enables the connection initiated by the poller option
    Then a new parameters group is displayed for the host
    When the user clicks to add a second host
    Then a second group of parameters for hosts is displayed
    When the user fills in the centreon agent parameters
    And the user clicks on Create
    Then the third agent is displayed in the Agents Configuration page

  @TEST_MON-151992
  Scenario: Add an agent with missing information
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in all the mandatory information
    Then the user cannot click on Create

  @TEST_MON-151993
  Scenario: Add an agent with incorrect information
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in correct type of information
    Then the form displayed an error

  @TEST_MON-151994
  Scenario: Cancel a creation form
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in the needed information
    And the user clicks on the Cancel button of the creation form
    Then a pop-up appears to confirm cancellation
    And the user confirms the cancellation
    Then the creation form is closed
    And the agent has not been created
    When the user clicks on Add
    Then the form fields are empty

  @TEST_MON-152203
  Scenario: Save during cancellation in a creation form
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in the needed information
    And the user clicks on the Cancel button of the creation form
    Then a pop-up appears to confirm cancellation
    And the user clicks on Save in the cancellation pop-up
    Then the creation form is closed
    And the agent has been created
    When the user clicks on Add
    Then the form fields are empty

  @TEST_MON-152745
  Scenario Outline: Verification of the pop-up displayed when user '<action>' a PAC form creation with '<agent_type>' type and all mandatory informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user selects the '<agent_type>' type
    And the user fills in the '<agent_type>' mandatory fields
    And the user '<action>' the form
    Then a pop-up is displayed
    And the title of this pop-up is '<popup_title>'
    And the message body of this pop-up is '<popup_message>'
    Examples:
      | action           | agent_type | popup_title                    | popup_message                                                             |
      | clicks on cancel | Centreon Monitoring Agent | Do you want to save the changes? | If you click on Discard, your changes will not be saved. |
      | clicks on cancel | Telegraf | Do you want to save the changes? | If you click on Discard, your changes will not be saved. |
      | clicks outside | Centreon Monitoring Agent | Do you want to save the changes? | If you click on Discard, your changes will not be saved. |
      | clicks outside | Telegraf | Do you want to save the changes? | If you click on Discard, your changes will not be saved. |

  @TEST_MON-152759
  Scenario Outline: Verification of the pop-up displayed when user '<action>' a PAC form creation with '<agent_type>' type and missing mandatory informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user selects the '<agent_type>' type
    And the user doesn't fill some '<agent_type>' mandatory fields
    And the user '<action>' the form
    Then a pop-up is displayed
    And the title of this pop-up is '<popup_title>'
    And the message body of this pop-up is '<popup_message>'
    And this pop-up contains two buttons "Leave" and "Stay"
    Examples:
      | action           | agent_type | popup_title                    | popup_message                                                             |
      | clicks on cancel | Centreon Monitoring Agent | Do you want to leave this page? | Your changes will not be saved if you leave this page. |
      | clicks on cancel | Telegraf | Do you want to leave this page? | Your changes will not be saved if you leave this page. |
      | clicks outside | Centreon Monitoring Agent | Do you want to leave this page? | Your changes will not be saved if you leave this page. |
      | clicks outside | Telegraf | Do you want to leave this page? | Your changes will not be saved if you leave this page. |