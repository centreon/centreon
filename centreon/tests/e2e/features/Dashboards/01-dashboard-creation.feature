@REQ_MON-18494
Feature: Creating a new dashboard
  As a Centreon User with dashboard edition rights,
  I need to start creating a dashboard starting from an empty page on which I will place widgets
  So these dashboards can be consulted by myself and other users

  @TEST_MON-22158
  Scenario: Create a new dashboard with required fields only
    Given a user with dashboard edition rights on the dashboard listing page
    When the user opens the form to create a new dashboard
    Then the creation form is displayed and contains the fields to create a dashboard
    When the user fills in the required fields
    Then the user is allowed to create the dashboard with the required fields only
    When the user saves the dashboard
    Then the user is redirected to the newly created dashboard
    And the newly created dashboard detail page is in edit mode
    And the newly created dashboard has the required only dashboard data

  @TEST_MON-22160
  Scenario: Create a new dashboard with name and description
    Given a user with dashboard edition rights on the dashboard creation form
    When the user fills in all the fields
    And the user saves the dashboard
    Then the user is redirected to the newly created dashboard
    And the newly created dashboard has the name and description the user filled in

  @TEST_MON-22159
  Scenario: Cancel a creation form
    Given a user with dashboard edition rights who is about to create a dashboard
    When the user leaves the creation form without saving the dashboard
    Then the user is on the dashboards overview page
    And the dashboard has not been created
    When the user opens the form to create a new dashboard for the second time
    Then the form fields are empty

  # uncomment once MON-37265 is fixed
  @TEST_MON-23634
  Scenario: Creating a new dashboard while editing an existing one
    Given a dashboard with existing widgets in the dashboard administrator user's dashboard library
    When the dashboard administrator user starts to edit the dashboard
    And creates a new dashboard on the previous dashboard's edition page
    Then the user is redirected to the newly created dashboard's edition page
    And the newly created dashboard is empty