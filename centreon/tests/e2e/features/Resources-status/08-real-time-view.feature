Feature: Resources Status real-time view
  As a Centreon user
  I want the listing and the detail panel of Resources Status to follow the results received by the monitoring
  So that I see the current state of my resources without reloading the page

  @MON-214305
  Scenario: A service whose state changes is updated in the listing by the autorefresh
    Given a host rbt-view-host with a passive service rbt-view-svc exported and listed in Resources status
    And the listing autorefresh is enabled
    When a Critical check result is submitted for rbt-view-svc outside the UI
    Then within one refresh interval the row of rbt-view-svc shows the status Critical and the submitted output without a page reload
    And rbt-view-svc is listed under the Unhandled alerts filter
    When an Ok check result is submitted for rbt-view-svc
    Then within one refresh interval rbt-view-svc leaves the Unhandled alerts listing
    And it is listed Ok under the All filter

  @MON-214306
  Scenario: The detail panel of a service shows its real-time details
    Given a service rbt-view-svc on host rbt-view-host listed in Resources status with a known last check result
    When the user clicks the row of rbt-view-svc
    Then the detail panel opens on the Details tab with the service name, its host and its status
    And the Status information, Last check and Monitoring server tiles match the last result stored for the service
    When a new check result is submitted for rbt-view-svc outside the UI
    Then within one refresh interval the panel shows the new status and output
    When the user opens the host rbt-view-host from the panel
    Then the Services tab lists rbt-view-svc with its status
