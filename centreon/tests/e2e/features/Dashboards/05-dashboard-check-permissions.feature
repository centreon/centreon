@REQ_MON-18516
Feature: Checking dashboard permissions
  As a Centreon User with dashboard edition rights,
  I need to list users with whom a dashboard has been shared,
  whether they can only read or also write, and update access rights if needed.

  @TEST_MON-22181
  Scenario: Accessing all dashboards as an admin user
    Given an admin user is logged in on a platform with dashboards
    When the admin user accesses the dashboards library
    Then the admin user can view all the dashboards configured on the platform
    When the admin user clicks on a dashboard
    Then the admin user is redirected to the detail page for this dashboard
    And the admin user is allowed to access the edit mode for this dashboard
    And the admin user is allowed to update the dashboard's properties

  @TEST_MON-22174
  Scenario: Creating a new dashboard as an admin user
    Given an admin user on the dashboards library
    When the admin user creates a new dashboard
    Then the dashboard is created and is noted as the creation of the admin user

  @TEST_MON-22176
  Scenario: Deleting a dashboard as an admin user
    Given an admin user who has just created a dashboard
    When the admin user deletes the newly created dashboard
    Then the admin's dashboard is deleted and does not appear anymore in the dashboards library

  @TEST_MON-22173
  Scenario: Accessing all dashboards as a non-admin dashboard administrator user
    Given a non-admin user with the dashboard administrator role is logged in on a platform with dashboards
    When the dashboard administrator user accesses the dashboards library
    Then the dashboard administrator user can consult all the dashboards configured on the platform
    When the dashboard administrator user clicks on a dashboard
    Then the dashboard administrator user is redirected to the detail page for this dashboard
    And the dashboard administrator user is allowed to access the edit mode for this dashboard
    And the dashboard administrator user is allowed to update the dashboard's properties

  @TEST_MON-22501
  Scenario: Creating a new dashboard as a non-admin dashboard administrator user
    Given a non-admin user with the administrator role on the dashboard feature
    When the dashboard administrator user creates a new dashboard
    Then the dashboard is created and is noted as the creation of the dashboard administrator user

  @TEST_MON-22177
  Scenario: Deleting a dashboard as a non-admin dashboard administrator user
    Given a dashboard administrator user who has just created a dashboard
    When the dashboard administrator user deletes the newly created dashboard
    Then the dashboard administrator's dashboard is deleted and does not appear anymore in the dashboards library

  @TEST_MON-22501
  Scenario: Accessing the list of shared dashboards as a non-admin dashboard editor
    Given a non-admin user with the dashboard editor role is logged in on a platform with dashboards
    When the dashboard editor user accesses the dashboards library
    Then a list of the dashboards the dashboard editor user has access to is displayed
    When the dashboard editor user clicks on a dashboard
    Then the dashboard editor user is redirected to the detail page for this dashboard
    And the dashboard editor user is allowed to access the edit mode for this dashboard
    And the dashboard editor user is allowed to update the dashboard's properties

  @TEST_MON-22175
  Scenario: Creating a new dashboard as a non-admin dashboard editor user
    Given a non-admin user with the editor role on the dashboard feature
    When the dashboard editor user creates a new dashboard
    Then the dashboard is created and is noted as the creation of the dashboard editor user

  @TEST_MON-22178
  Scenario: Deleting a dashboard as a non-admin dashboard editor user
    Given a dashboard editor user who has just created a dashboard
    When the dashboard editor user deletes the newly created dashboard
    Then the dashboard editor's dashboard is deleted and does not appear anymore in the dashboards library

  @TEST_MON-22180
  Scenario: Accessing the list of shared dashboards as a non-admin dashboard viewer
    Given a non-admin user with the dashboard viewer role is logged in on a platform
    When the dashboard viewer user accesses the dashboards library
    Then a list of the dashboards the dashboard viewer user has access to is displayed
    When the dashboard viewer user clicks on a dashboard
    Then the dashboard viewer user is redirected to the detail page for this dashboard
    And the dashboard viewer user does not have access to any update or share-related options on a dashboard

  @TEST_MON-22179
  Scenario: Inability to create a new dashboard as a non-admin dashboard viewer
    Given a non-admin user with the viewer role on the dashboard feature
    When the dashboard viewer accesses the dashboards library
    Then the option to create a new dashboard is not displayed

  @TEST_MON-22182
  Scenario: Inability to delete a dashboard as a non-admin dashboard viewer
    Given a dashboard viewer user who could not create a dashboard
    When the dashboard viewer user tries to delete a dashboard
    Then the button to delete a dashboard does not appear