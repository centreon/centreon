Feature: Notified contact groups inherited from several host templates
  As a Centreon administrator
  I want the legacy Status Details pages to open for a host whose contact groups come from several templates
  So that I can see who is notified for the host and its services

  @MON-215269
  Scenario: Opening a host and a service whose contact groups are inherited from several templates
    Given an administrator is logged in
    And the deprecated monitoring pages are enabled
    And the contacts and contact groups calculation method is cumulative inheritance
    Given two host templates notifying the same contact group
    And a host using both templates and notifying another contact group
    And the host is monitored by a poller with the configuration exported
    When the administrator opens the host in the legacy Status Details page
    Then the host details page is displayed
    And each notified contact group is listed once
    When the administrator opens one of the host's services from that page
    Then the service details page is displayed
    And the notified contacts are listed
