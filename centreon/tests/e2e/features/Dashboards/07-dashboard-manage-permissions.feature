@REQ_MON-18519
Feature: Managing dashboard permissions
  As a Centreon User with rights to manage users,
  I need to configure the roles assigned with regards to the dashboarding feature.
  I can define whether users can manage access rights to dashboards (i.e. administrator right to the dashboarding capability).

  @TEST_MON-22193
  Scenario: Promote a viewer user to an editor on a dashboard
    Given a dashboard featuring a dashboard administrator and a dashboard viewer in its share list
    When the dashboard administrator user promotes the viewer user to an editor
    Then the now-editor user can now perform update operations on the dashboard

  @TEST_MON-22192
  Scenario: Demote an editor user to a viewer on a dashboard
    Given a dashboard featuring a dashboard administrator and a dashboard editor in its share list
    When the dashboard administrator user demotes the editor user to a viewer
    Then the now-viewer user cannot perform update operations on the dashboard anymore

  @TEST_MON-22195
  Scenario: Remove read permissions on a dashboard to a user
    Given a dashboard featuring a dashboard administrator and a viewer in its share list
    When the dashboard administrator user removes the dashboard editor user from the share list
    Then the dashboard is not visible anymore in the non-admin user's dashboards library

  @TEST_MON-22194
  Scenario: Revert a user removal in the share list of a dashboard
    Given a dashboard featuring a dashboard administrator and a user who has just been removed from the share list
    When the dashboard administrator user restores the deleted user to the share list and saves
    Then the restored user retains the same rights on the dashboard