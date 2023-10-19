#testSet:MON-23576
@REQ_MON-23575
Feature: this is just a test
  As a Centreon User with dashboard edition rights,
  I need to start creating a dashboard starting from an empty page on which I will place widgets
  So these dashboards can be consulted by myself and other users

  Scenario: This is just a test
    Given a user with dashboard edition rights on the dashboard listing page
    When the user opens the form to create a new dashboard
    Then the creation form is displayed and contains the fields to create a dashboard
    When the user fills in the required fields
    Then the user is allowed to create the dashboard with the required fields only
    When the user saves the dashboard
    Then the user is redirected to the newly created dashboard

