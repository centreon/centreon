@REQ_MON-113483
Feature: Managing favorites using the web page widget
  As a Centreon User with dashboard update rights,
  I need to configure a web page widget to manage favorite dashboards
  to easily add or remove dashboards from the favorites list.

  @TEST_MON-156343
  Scenario: Adding dashbaord to favourites
    Given a dashboard having a configured web page widget
    When the dashboard administrator clicks on the favourite icon
    Then the dashboard is added to the favourites list

  @TEST_MON-156343
  Scenario: Removing dashbaord from favourites
    Given a dashboard having another configured web page widget
    When the dashboard administrator clicks on the favourite icon of the first dashboard in the favourites list
    Then the dashboard should be removed from the favourites list