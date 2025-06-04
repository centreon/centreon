Feature: Custom views
  As a Centreon user
  I want to share my custom views
  So that other users can benefit from it

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-162783
  Scenario: Create a locked shared view
    Given the admin is on the "Home > Custom Views" page
    When the admin adds a new locked custom view shared with a configured non admin user
    Then the view is added

  @TEST_MON-162784
  Scenario: Share read-only custom view with users
    Given a custom view shared in read only with the non admin user
    When the non admin user wishes to add a new custom view
    Then he can add the shared view
    And he cannot modify the content of the shared view

  @TEST_MON-162794
  Scenario: Remove read-only custom view shared with users
    Given a custom view shared in read only with the non admin user
    And the non admin user is using the shared view
    When he removes the shared view
    Then the view is not visible anymore
    And the user can use the shared view again

  @TEST_MON-162795
  Scenario: Update a read only custom view shared with users
    Given a custom view shared in read only with the non admin user
    And the non admin user is using the shared view
    When the owner modifies the custom view
    Then the changes are reflected on all users displaying the custom view

  @TEST_MON-162797
  Scenario: Delete a shared custom view
    Given a custom view shared in read only with the non admin user
    And the non admin user is using the shared view
    When the owner removes the view
    Then the view is removed for all users displaying the custom view

  @TEST_MON-162798
  Scenario: Share read-only custom view with groups
    Given a custom view shared in read only with a group
    When the non admin user wishes to add a new custom view
    Then he can add the shared view
    And he cannot modify the content of the shared view

  @TEST_MON-162799
  Scenario: Remove read-only custom view shared with groups
    Given a configured custom view shared in read only with a group
    And an user of this group is using the configured shared view
    When he removes the shared view
    Then the view is not visible anymore
    And the user can use the shared view again

  @TEST_MON-162800
  Scenario: Update a read only custom view shared with groups
    Given a configured custom view shared in read only with a group
    And an user of this group is using the configured shared view
    When the owner modifies the custom view
    Then the changes are reflected on all users displaying the custom view

  @TEST_MON-162801
  Scenario: Delete a shared custom view with groups
    Given a configured custom view shared in read only with a group
    And an user of this group is using the configured shared view
    When the owner removes the view
    Then the view is removed for all users displaying the custom view