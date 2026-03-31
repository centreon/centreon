Feature: Modern services by host and services by hostgroup listings
    As a Centreon admin
    I want to manage services from the modernized listing pages
    With AJAX search, toggle, RTM status badges, host grouping and pagination

  Background:
    Given an admin user is logged in Centreon
    And hosts with services exist

  @TEST_LISTING-173
  Scenario: Services by host listing loads via AJAX
    When the user navigates to the services by host listing
    Then the AJAX listing table is displayed with service rows grouped by host

  @TEST_LISTING-174
  Scenario: Search filters services by name
    When the user navigates to the services by host listing
    And the user searches for a specific service
    Then only the matching services are displayed

  @TEST_LISTING-175
  Scenario: Toggle disables a service
    When the user navigates to the services by host listing
    And the user clicks the toggle to disable a service
    Then the service toggle switches to disabled
    And the toggle response is successful

  @TEST_LISTING-176
  Scenario: Toggle enables a service
    When the user navigates to the services by host listing
    And the service is disabled
    And the user clicks the toggle to enable the service
    Then the service toggle switches to enabled

  @TEST_LISTING-177
  Scenario: RTM monitoring status badges are displayed
    When the user navigates to the services by host listing
    Then service rows have monitoring status badges

  @TEST_LISTING-178
  Scenario: Pagination and rows per page
    When the user navigates to the services by host listing
    Then the pagination info shows the total count

  @TEST_LISTING-179
  Scenario: Bulk duplication works
    When the user navigates to the services by host listing
    And the user selects a service and duplicates it
    Then a duplicated service appears in the listing

  @TEST_LISTING-180
  Scenario: Clicking a service name navigates to the edit form
    When the user navigates to the services by host listing
    And the user clicks on a service name
    Then the service edit form is displayed

  @TEST_LISTING-181
  Scenario: Services by hostgroup listing loads via AJAX
    When the user navigates to the services by hostgroup listing
    Then the AJAX listing table is displayed with hostgroup service rows

  @TEST_LISTING-182
  Scenario: Session state persists on services by host listing
    When the user navigates to the services by host listing
    And the user searches for a specific service
    And the user clicks on a service name
    And the user navigates back to the services by host listing
    Then the search field still contains the search term
