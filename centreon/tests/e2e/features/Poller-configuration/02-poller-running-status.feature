@REQ_MON-192897
Feature: Poller running status on the configuration page
  As a Centreon user
  I want the pollers configuration page to reflect the real running status of each poller
  So that a legacy poller is not wrongly shown as not running on a mixed-version platform

  Background:
    Given an admin user is logged in a Centreon server

  Scenario: A poller reporting its uid is displayed as running
    Given a poller is running and reports its uid as runtime instance id
    When the user opens the pollers configuration page
    Then the seeded poller is displayed as running

  Scenario: A legacy poller reporting its config id is displayed as running
    Given a legacy poller is running and reports its config id as runtime instance id
    When the user opens the pollers configuration page
    Then the seeded legacy poller is displayed as running
