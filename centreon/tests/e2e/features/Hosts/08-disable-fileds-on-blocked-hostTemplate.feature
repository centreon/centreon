Feature: Freeze fields on blocked host templates
  As a Centreon admin
  I want to freeze all the fields of a blocked host template
  To let the user know that the content is in read-only

  Background:
    Given a user is logged in a Centreon server

  @TEST_MON-159902
  Scenario: Block a host template
    Given a blocked host template
    When the user goes to the host template listing page
    Then the blocked host template is not visible on the page
    When the user check the checkbox "Locked elements"
    And the user clicks on the Search button
    Then the blocked host template is visible on the page
    When the user opens the form of the blocked host template
    Then the fields are all frozen