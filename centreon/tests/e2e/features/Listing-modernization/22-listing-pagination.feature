@REQ_MON-200027
Feature: The migrated listings paginate on a real total
  As a Centreon administrator
  I want the pagination to report the true number of rows and to navigate them
  So that no configured object becomes unreachable behind a wrong count

  # The count is computed by a query separate from the one fetching the rows, so
  # a listing can render a correct page while reporting a total that hides the
  # last one. Seeding more objects than a page holds is what makes the limit an
  # actual constraint here.

  Background:
    Given a user is logged in a Centreon server
    And more service groups exist than a single page holds

  # Cloud: identical
  Scenario: The total counts every row, not just the displayed page
    When the user navigates to the service groups listing
    And the user changes the rows per page to 10
    Then exactly 10 rows are displayed
    And the pagination total matches the number of service groups in the database

  # Cloud: identical
  Scenario: The next page shows the following rows
    Given the user is on the first page of ten service groups
    When the user goes to the next page
    Then the pagination window moves to the second page
    And the rows differ from the first page

  # Cloud: identical
  Scenario: The last page is reachable and not empty
    Given the user is on the first page of ten service groups
    When the user goes to the last page
    Then the last page holds at least one row
    And the next and last controls are disabled
