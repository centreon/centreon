Feature: Configuration listing interactions
  As a Centreon administrator
  I want a configuration listing to search, sort, page, keep my columns and act on the rows I select
  So that I find and manage my objects quickly

  @MON-214342
  Scenario: A configuration listing searches, sorts, pages, keeps its columns and acts on selected rows
    Given an admin user is logged in a Centreon server
    And 12 host groups named from "rbt-ui-hg-01" to "rbt-ui-hg-12" exist
    When the user searches "rbt-ui-hg" in the host groups listing
    And the user shows 10 rows per page sorted by "Name" in descending order
    Then the first page lists "rbt-ui-hg-12" down to "rbt-ui-hg-03"
    When the user goes to the next page
    Then the page lists only "rbt-ui-hg-02" and "rbt-ui-hg-01"
    When the user hides the "Alias" column and reloads the page
    Then the "Alias" column is still hidden
    When the user selects "rbt-ui-hg-11" and "rbt-ui-hg-12" and disables them from "More actions"
    Then the message "Host groups disabled" is displayed and both rows are disabled
