Feature: Timezone in monitoring
    As a Centreon user
    I want to be able to use timezones across the platform
    To manage and visualize the monitoring resources more accurately

Background:
    Given a user authenticated in a Centreon server
    And the platform is configured with at least one resource

Scenario: Configuring timezone in user's profile
   When the user clicks on Timezone field in his profile menu
   And the user selects a Timezone / Location
   And the user saves the form
   Then timezone information are updated on the banner
   And new timezone information is displayed in user's profile menu

# Scenario: User can set a realtime downtime with a custom timezone in Monitoring>Resource Status
#   Given a user with a custom timezone set in his profile
#   When the user creates a downtime on a resource
#   Then date and time fields should be based on the custom timezone of the user

# Scenario: User can set a realtime downtime with a custom timezone in Monitoring>Downtime
#   Given a user with a custom timezone set in his profile
#   When the user creates a downtime on a resource in Monitoring>Downtime
#   Then date and time fields should be based on the custom timezone of the user in Monitoring>Downtime

Scenario: User can set a acknowledgement with a custom timezone in Monitoring>Resource Status
  Given a user with a custom timezone set in his profile
  When the user creates an acknowledgement on a resource
  Then date and time fields of acknowledge resource should be based on the custom timezone of the user

Scenario: User can visualize charts in detail panel with a custom timezone
  Given a user with a custom timezone set in his profile
  When the user opens a chart from resource status detail panel
  And the user selects a date and time in the graph tab of detail panel
  Then the time window of the chart is based on the custom timezone of the user