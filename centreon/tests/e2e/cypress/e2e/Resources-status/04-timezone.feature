Feature: Timezone in monitoring
  As a Centreon user
  I want to be able to use timezones across the platform
  To manage and visualize the monitoring resources more accurately

  Background:
    Given a user authenticated in a Centreon server
    And the platform is configured with at least one resource

  Scenario: User can set a realtime downtime with a custom timezone in Monitoring>Downtime
    Given a user with a custom timezone set in his profile
    When the user creates a downtime on a resource in Monitoring>Downtime
    Then date and time fields should be based on the custom timezone of the user in Monitoring>Downtime
