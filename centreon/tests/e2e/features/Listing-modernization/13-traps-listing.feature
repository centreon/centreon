Feature: Modern SNMP traps, manufacturers and trap groups listings
    As a Centreon admin
    I want to manage traps, manufacturers and trap groups from modernized listings
    With AJAX search, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  Scenario: SNMP traps listing loads via AJAX
    When the user navigates to the traps listing
    Then the AJAX listing table is displayed with trap rows

  Scenario: Search filters traps by name
    Given a trap definition exists
    When the user navigates to the traps listing
    And the user searches for a specific trap
    Then only the matching trap is displayed

  Scenario: Traps bulk duplication works
    Given a trap definition exists
    When the user navigates to the traps listing
    And the user selects a trap and duplicates it
    Then a duplicated trap appears in the listing

  Scenario: Traps bulk deletion works
    Given a trap definition exists
    When the user navigates to the traps listing
    And the user selects a trap and deletes it
    Then the trap is removed from the listing

  Scenario: Manufacturer listing loads via AJAX
    When the user navigates to the manufacturers listing
    Then the AJAX listing table is displayed with manufacturer rows

  Scenario: Search filters manufacturers by name
    Given a manufacturer exists
    When the user navigates to the manufacturers listing
    And the user searches for a specific manufacturer
    Then only the matching manufacturer is displayed

  Scenario: Manufacturer bulk duplication works
    Given a manufacturer exists
    When the user navigates to the manufacturers listing
    And the user selects a manufacturer and duplicates it
    Then a duplicated manufacturer appears in the listing

  Scenario: Trap groups listing loads via AJAX
    When the user navigates to the trap groups listing
    Then the AJAX listing table is displayed with trap group rows

  Scenario: Search filters trap groups by name
    Given a trap group exists
    When the user navigates to the trap groups listing
    And the user searches for a specific trap group
    Then only the matching trap group is displayed

  Scenario: Trap groups bulk duplication works
    Given a trap group exists
    When the user navigates to the trap groups listing
    And the user selects a trap group and duplicates it
    Then a duplicated trap group appears in the listing

  Scenario: Session state persists on traps listing
    Given a trap definition exists
    When the user navigates to the traps listing
    And the user searches for a specific trap
    And the user clicks on the trap name
    And the user navigates back to the traps listing
    Then the search field still contains the search term
