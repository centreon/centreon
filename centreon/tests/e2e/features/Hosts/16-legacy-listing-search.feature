Feature: Search of a legacy listing
  As a Centreon administrator
  I want the search typed in a legacy listing to be handled as plain text
  So that quotes, SQL-like text or markup never break, alter or get interpreted in the listing

  @MON-215219
  Scenario Outline: Search a legacy listing with <search_text> and see the results displayed safely
    Given an administrator is logged in
    # A host name cannot hold a quote nor an ampersand: they are in the illegal
    # characters of the Engine configuration and removed from the name, the alias keeps them
    And a host with the alias "web'srv&1" is configured
    Given the administrator is on the hosts configuration listing
    When the administrator searches the listing for "<search_text>"
    Then no error message is displayed
    And the listing only shows the hosts whose name contains "<search_text>"
    And the typed text is displayed as plain text
    # The hosts listing has no sortable column: the sort order is sent as the
    # parameter the listing pages carry
    When the administrator reloads the listing with a descending sort order
    Then no error message is displayed
    And the search filter is still applied
    When the administrator clears the search
    Then the full hosts listing is displayed

    Examples:
      | search_text      |
      | web'srv&1        |
      | ' OR '1'='1      |
      | <b>test</b>      |
      | %_\              |
