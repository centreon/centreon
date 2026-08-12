Feature: HostTemplateBasicsOperations
  As a Centreon admin
  I want to manipulate a host template
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a host template is configured

  @MON-151104
  Scenario: Edit of a host template properties
    When the user changes the properties of the configured host template
    Then the properties are updated

  @MON-151105
  Scenario: Duplication of a host template
    When the user duplicates the configured host template
    Then a new host template is created with identical properties

  @MON-151109
  Scenario: Deletion of a host template
    When the user deletes the configured host template
    Then the deleted host template is not visible anymore on the host template page

  Scenario: Mass Change applies the same properties to several host templates
    Given a second host template is configured
    When the user applies a mass change on both host templates
    Then both host templates carry the mass changed values

  Scenario: The host templates listing loads through the AJAX framework
    When the user opens the host templates listing
    Then the AJAX listing table is displayed with the configured host template

  Scenario: The search filters the host templates by name
    Given a second host template is configured
    When the user opens the host templates listing
    And the user searches the host templates for the first one
    Then only the matching host template is displayed

  Scenario: A locked host template offers no row action
    Given the configured host template is locked
    When the user opens the host templates listing
    And the user asks for the locked host templates
    Then the locked host template cannot be selected nor duplicated

  Scenario: The listing paginates and honours the rows-per-page selector
    When the user opens the host templates listing
    Then the pagination information shows the total count of host templates
    When the user sets the rows per page to 10
    Then at most 10 host template rows are displayed

  Scenario: The search term persists across navigation
    When the user opens the host templates listing
    And the user searches the host templates for the first one
    And the user opens the host template form and comes back to the listing
    Then the host templates search field still contains the search term
