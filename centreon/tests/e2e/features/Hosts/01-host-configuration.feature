Feature: HostConfiguration
  As a Centreon admin
  I want to modify a host
  To see if the modification is saved on the Host Page

  Background:
    Given an admin user is logged in a Centreon server
    And a host is configured

  @MON-177105
  Scenario: Edit the name of a host
    When the admin changes the name of a host to "Edited Host Name"
    Then the updated name should be updated on the host page to "Edited Host Name"

  @MON-177106
  Scenario: Duplicate one existing host
    When the admin duplicates a host
    Then a new host is created with identical fields

  @MON-177107
  Scenario: Delete one existing host
    When the admin deletes the host
    Then the host is not visible in the host list

  @MON-177011
  Scenario: Add a host with geo-coordinates exceeding 32 characters
    Given the admin is on the hosts listing page
    And the admin fills in the required fields to create a host
    And the admin enters this non valid value "48.85503400000000000000,2.34667000000000000000" in the geo-coordinates field
    When the admin saves the host
    Then the host is successfully created
    And the geo-coordinates value is truncated "48.855034,2.346670"

  @MON-177012
  Scenario: Edit an existing host and enter geo-coordinates longer than 32 characters
    Given the admin is on the hosts listing page
    And a host is already configured
    When the admin opens the edit form on this host
    And the admin enters this non valid value "48.85503400000000000000,2.34667000000000000000" in the geo-coordinates field
    When the admin saves the host
    Then the host is successfully created
    And the geo-coordinates value is truncated "48.855034,2.346670"

  Scenario: The hosts listing loads through the AJAX framework
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the AJAX listing table is displayed with the configured hosts
    And each host row carries its address and poller

  Scenario: The search filters the hosts by name
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    And the admin searches the hosts for "host_alpha"
    Then only the matching host is displayed in the hosts listing

  Scenario: The search filters the hosts by address
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    And the admin searches the hosts for "192.168"
    Then only the host carrying that address is displayed

  Scenario: The hostgroup advanced filter restricts the listing
    Given several hosts exist with different addresses
    And the first host belongs to a dedicated hostgroup
    When the admin opens the hosts listing
    And the admin filters the listing on that hostgroup
    Then only the hosts of that hostgroup are displayed
    When the admin clears the advanced filters
    Then all the hosts are displayed again

  Scenario: Enable and disable a host from the listing toggle
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    And the admin toggles the first host off from the listing
    Then the toggle request succeeds and the host is disabled
    When the admin toggles the first host on from the listing
    Then the host is enabled again

  Scenario: Each host row shows an icon inherited from its template chain
    Given several hosts exist with different addresses
    And the first host carries its own icon and its template another one
    When the admin opens the hosts listing
    Then the first host shows its own icon and the others their template one

  Scenario: The toggle endpoint refuses a request carrying an invalid CSRF token
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the toggle endpoint answers 403 and the host stays enabled

  Scenario: The toggle endpoint refuses a user without write access on hosts
    Given several hosts exist with different addresses
    And a user without write access on hosts is logged in
    Then the toggle endpoint answers 403 to that user and the host stays enabled

  Scenario: The monitoring column shows a status badge or a placeholder
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the monitoring column shows a tooltipped badge or the not-monitored placeholder

  Scenario: The template column links to the host template form
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the template of the first host opens the host template side panel

  Scenario: The options column links to the services of the host
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then every host row links to its own services

  Scenario: The listing paginates and honours the rows-per-page selector
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the pagination information shows the total count of hosts
    When the admin sets the rows per page to 10
    Then at most 10 host rows are displayed

  Scenario: The header checkbox selects and deselects every row
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    And the admin clicks the header checkbox
    Then every host row checkbox is checked
    When the admin clicks the header checkbox
    Then every host row checkbox is unchecked

  Scenario: The listing refreshes on its own without reloading the page
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    Then the listing issues a new AJAX request on its own

  Scenario: The search term persists across navigation
    Given several hosts exist with different addresses
    When the admin opens the hosts listing
    And the admin searches the hosts for "host_alpha"
    And the admin opens the host form and comes back to the listing
    Then the hosts search field still contains the search term
