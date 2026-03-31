Feature: Modern dependency listings (host, hostgroup, service, servicegroup, metaservice)
    As a Centreon admin
    I want to manage all dependency types from modernized listing pages
    With AJAX search, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  Scenario: Host dependency listing loads via AJAX
    Given a host dependency exists
    When the user navigates to the host dependencies listing
    Then the AJAX listing table is displayed with dependency rows

  Scenario: Search filters host dependencies
    Given a host dependency exists
    When the user navigates to the host dependencies listing
    And the user searches for a specific dependency
    Then only the matching dependency is displayed

  Scenario: Host dependency bulk duplication
    Given a host dependency exists
    When the user navigates to the host dependencies listing
    And the user selects a dependency and duplicates it
    Then a duplicated dependency appears in the listing

  Scenario: Hostgroup dependency listing loads via AJAX
    Given a hostgroup dependency exists
    When the user navigates to the hostgroup dependencies listing
    Then the AJAX listing table is displayed with dependency rows

  Scenario: Service dependency listing loads via AJAX
    Given a service dependency exists
    When the user navigates to the service dependencies listing
    Then the AJAX listing table is displayed with dependency rows

  Scenario: Servicegroup dependency listing loads via AJAX
    Given a servicegroup dependency exists
    When the user navigates to the servicegroup dependencies listing
    Then the AJAX listing table is displayed with dependency rows

  Scenario: Metaservice dependency listing loads via AJAX
    Given a metaservice dependency exists
    When the user navigates to the metaservice dependencies listing
    Then the AJAX listing table is displayed with dependency rows

  Scenario: Session state persists on host dependency listing
    Given a host dependency exists
    When the user navigates to the host dependencies listing
    And the user searches for a specific dependency
    And the user clicks on a dependency name
    And the user navigates back to the host dependencies listing
    Then the search field still contains the search term
