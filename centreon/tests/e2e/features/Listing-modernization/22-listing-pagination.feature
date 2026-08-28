@REQ_MON-200027
Feature: The migrated listings paginate on a real total
  As a Centreon administrator
  I want the pagination to report the true number of rows and to navigate them
  So that no configured object becomes unreachable behind a wrong count

  # The count comes from a query separate from the one fetching the rows, so a
  # listing can render a correct page while reporting a total that hides the
  # last one. Seeding more objects than a page holds is what turns the limit
  # into an actual constraint.

  Background:
    Given a user is logged in a Centreon server

  # Cloud: identical
  Scenario: The total counts every row, not just the displayed page
    Given more service groups exist than a single page holds
    When the user navigates to the service groups listing
    And the user changes the rows per page to 10
    Then exactly 10 rows are displayed
    And the pagination total matches the number of service groups in the database

  # Cloud: identical
  Scenario: The next page shows the following rows
    Given more service groups exist than a single page holds
    And the user is on the first page of ten service groups
    When the user goes to the next page
    Then the pagination window moves to the second page
    And none of the first page rows are shown again

  # Cloud: identical
  Scenario: The last page is reachable and not empty
    Given more service groups exist than a single page holds
    And the user is on the first page of ten service groups
    When the user goes to the last page
    Then the last page holds the rows the counter announces
    And the next and last controls are disabled

  # This is the page the count defect actually lived on: its total is a COUNT
  # over a derived table of (service, host) pairs, and the ACL join repeats a
  # pair per access group. Service groups cannot reproduce it.
  # Cloud: identical
  Scenario: A service on several hosts is counted once per host
    Given a service is attached to two hosts
    When the user opens the services by host listing at ten per page
    Then the total counts the service once per host it is attached to
    And the last page of the services by host listing is not empty
