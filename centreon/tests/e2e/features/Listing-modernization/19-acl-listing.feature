Feature: Modern ACL listings (groups, menus, actions, resources)
    As a Centreon admin
    I want to manage ACL configurations from modernized listing pages
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  Scenario: ACL groups listing loads via AJAX
    When the user navigates to the ACL groups listing
    Then the AJAX listing table is displayed with ACL group rows

  Scenario: Search filters ACL groups by name
    When the user navigates to the ACL groups listing
    And the user searches for a specific ACL group
    Then only the matching ACL group is displayed

  Scenario: Toggle disables an ACL group
    Given an ACL group exists
    When the user navigates to the ACL groups listing
    And the user clicks the toggle to disable an ACL group
    Then the ACL group toggle switches to disabled

  Scenario: ACL menus listing loads via AJAX
    When the user navigates to the ACL menus listing
    Then the AJAX listing table is displayed with ACL menu rows

  Scenario: Toggle disables an ACL menu
    When the user navigates to the ACL menus listing
    And the user clicks the toggle on an ACL menu row
    Then the ACL menu toggle response is successful

  Scenario: ACL actions listing loads via AJAX
    When the user navigates to the ACL actions listing
    Then the AJAX listing table is displayed with ACL action rows

  Scenario: Toggle disables an ACL action
    When the user navigates to the ACL actions listing
    And the user clicks the toggle on an ACL action row
    Then the ACL action toggle response is successful

  Scenario: ACL resources listing loads via AJAX
    When the user navigates to the ACL resources listing
    Then the AJAX listing table is displayed with ACL resource rows

  Scenario: Toggle disables an ACL resource
    When the user navigates to the ACL resources listing
    And the user clicks the toggle on an ACL resource row
    Then the ACL resource toggle response is successful

  Scenario: Bulk duplication works on ACL groups
    Given an ACL group exists
    When the user navigates to the ACL groups listing
    And the user selects an ACL group and duplicates it
    Then a duplicated ACL group appears in the listing

  Scenario: Session state persists on ACL groups listing
    When the user navigates to the ACL groups listing
    And the user searches for a specific ACL group
    And the user clicks on an ACL group name
    And the user navigates back to the ACL groups listing
    Then the search field still contains the search term
