Feature: URI
  As a Centreon user
  I want to add URIs in plugin output or in comments
  To access the link from Centreon

  Background:
    Given a user is logged in a Centreon server

  @TEST_MON-160981
  Scenario: Add URI in service output
    Given a configured passive host
    And a configured passive service linked to the host
    When the user goes to "Administration > Parameters > My Account"
    And the user check the option "Use deprecated pages"
    And the user clicks on "Save"
    Then the user can access to the page "Monitoring > Status Details > Services"
    When the user submits result for the configured service
    And the user puts a link as "Check output"
    And the user save the modifications
    Then the status of the service is changed
    When the user clicks on the link in the "status information"
    Then a new tab is open to the link

  @TEST_MON-160982
  Scenario: Add URI in comments
    When the user visits "Monitoring > Status Details > Services"
    And the user adds a comment to a configured passive service
    Then the comment is displayed on "Monitoring > Downtimes > Comments" listing page
    When the user clicks on the link
    Then a new tab is open to the link