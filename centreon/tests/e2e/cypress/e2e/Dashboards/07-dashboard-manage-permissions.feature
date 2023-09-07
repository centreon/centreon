Feature: As a Centreon User with dashboard administration rights,
  I need to list all dashboards and user/user group rights to each, and update access rights if needed
  so that I can provision accesses or re-direct ownership if a user is not supposed to use Centreon anymore

Scenario: Promote a viewer user to an editor on a dashboard
  Given a dashboard featuring a dashboard administrator and a dashboard viewer in its share list
  When the dashboard administrator user promotes the viewer user to an editor
  Then the now-editor user can now perform update operations on the dashboard

Scenario: Demote an editor user to a viewer on a dashboard
  Given a dashboard featuring a dashboard administrator and a dashboard editor in its share list
  When the dashboard administrator user demotes the editor user to a viewer
  Then the now-viewer user cannot perform update operations on the dashboard anymore

# Scenario: Remove read permissions on a dashboard to a user
# Given a dashboard featuring a user with update rights and a user with viewing rights in its share list
# When the admin user removes the non-admin user from the share list
# Then the dashboard is not visible anymore in the non-admin user's dashboards library