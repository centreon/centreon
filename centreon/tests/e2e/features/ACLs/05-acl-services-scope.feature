@REQ_MON-200027
Feature: Service listings honour the resource ACL
  As a Centreon administrator
  I want the migrated service pages to enforce the ACL at the resource level
  So that a non-admin cannot see or alter a service outside its access group

  # Personas are deliberately not parameterised here: isAdmin() short-circuits
  # every branch under test, so only a non-admin with a restricted resource ACL
  # exercises this code at all.

  Background:
    Given a non-admin user whose ACL grants a single host

  # Cloud: identical
  Scenario: The services listing only shows services within the ACL scope
    When the non-admin user opens the services by host listing
    Then only the service of the granted host is listed

  # Cloud: identical
  Scenario: Toggling a service outside the ACL scope is refused
    When the non-admin user posts a toggle for the service of the denied host
    Then the toggle endpoint answers 403
    And the denied service is still enabled in the database

  # Cloud: identical
  Scenario: A refused toggle does not burn the page CSRF token
    Given the non-admin user has been refused a toggle outside its scope
    When the non-admin user toggles the service of the granted host
    Then the granted service is disabled in the database

  # Cloud: identical
  Scenario: Service categories stay visible when the ACL restricts none
    When the non-admin user opens the service categories listing
    Then the service categories listing is not empty
