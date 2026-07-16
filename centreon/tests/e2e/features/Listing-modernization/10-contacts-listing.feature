Feature: Modern contacts, contact templates and contact groups listings
    As a Centreon admin
    I want to manage contacts, templates and groups from modernized listings
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And test contacts and groups exist

  Scenario: Contact listing loads via AJAX
    When the user navigates to the contacts listing
    Then the AJAX listing table is displayed with contact rows

  Scenario: Search filters contacts by name
    When the user navigates to the contacts listing
    And the user searches for a specific contact
    Then only the matching contact is displayed

  Scenario: Toggle disables a contact
    When the user navigates to the contacts listing
    And the user clicks the toggle to disable a contact
    Then the contact toggle switches to disabled
    And the toggle response is successful

  Scenario: Cannot toggle your own account
    When the user navigates to the contacts listing
    Then the admin user toggle is disabled and not clickable

  Scenario: Bulk duplication and deletion on contacts
    When the user navigates to the contacts listing
    And the user selects a contact and duplicates it
    Then a duplicated contact appears in the listing
    When the user selects the duplicated contact and deletes it
    Then the duplicated contact is no longer listed

  Scenario: Contact template listing loads via AJAX
    When the user navigates to the contact templates listing
    Then the AJAX listing table is displayed with contact template rows

  Scenario: Search filters contact templates
    When the user navigates to the contact templates listing
    And the user searches for a specific contact template
    Then only the matching contact template is displayed

  Scenario: Toggle on contact template works
    When the user navigates to the contact templates listing
    And the user clicks the toggle to disable a contact template
    Then the contact template toggle switches to disabled

  Scenario: Contact group listing loads via AJAX
    When the user navigates to the contact groups listing
    Then the AJAX listing table is displayed with contact group rows

  Scenario: Search filters contact groups
    When the user navigates to the contact groups listing
    And the user searches for a specific contact group
    Then only the matching contact group is displayed

  Scenario: Toggle on contact group works
    When the user navigates to the contact groups listing
    And the user clicks the toggle to disable a contact group
    Then the contact group toggle switches to disabled

  Scenario: Session state persists on contacts listing
    When the user navigates to the contacts listing
    And the user searches for a specific contact
    And the user clicks on the contact name
    And the user navigates back to the contacts listing
    Then the search field still contains the search term
