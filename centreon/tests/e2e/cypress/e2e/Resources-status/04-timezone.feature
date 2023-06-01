Feature: Timezone in monitoring
    As a Centreon user
    I want to be able to use timezones across the platform
    To manage and visualize the monitoring resources more accurately

Background:
    Given a user authenticated in a Centreon server
    And the platform is configured with at least one resource

Scenario: Configuring timezone in user's profile
   When user cliks on Timezone field in his profile menu
   And user selects a Timezone / Location
   And user saves the form
   Then timezone information are updated on the banner
   And new timezone information is displayed in user's profile menu

Scenario: User can set a realtime downtime with a custom timezone in Monitoring>Resource Status
  Given a user with a custom timezone set in his profile
  When the user creates a downtime on a resource
  Then date and time fields should be based on the custom timezone of the user