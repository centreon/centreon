Feature: Custom views
  As a Centreon user
  I want to share my custom views
  So that other users can benefit from it

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-162286
  Scenario: Create an unlocked shared view
    Given the admin is on the "Home > Custom Views" page
    When the admin adds a new unlocked custom view shared with a configured non admin user
    Then the view is added

  @TEST_MON-162287
  Scenario: Modify an unlocked shared view
    Given a shared custom view with the non admin user
    When the non admin user is using the shared view
    Then he can modify the content of the shared view

  @TEST_MON-162288
  Scenario: Remove an unlocked shared view
    Given a shared custom view with the non admin user
    And the non admin user is using the configured shared view
    When he removes the shared view
    Then the view is not visible anymore
    And the user can use the shared view again

  @TEST_MON-162289
  Scenario: Modify an unlocked shared view and applies changes
    Given a shared custom view with the non admin user
    And the non admin user is using the configured shared view
    When the user modifies the custom view
    Then the changes are reflected on all users displaying the custom view

  @TEST_MON-162290
  Scenario: Deletion of an unlocked shared view
    Given a shared custom view with the non admin user
    And the non admin user is using the configured shared view
    When the owner removes the view
    Then the view is removed for the owner
    And the view remains visible for all users displaying the custom view

  @TEST_MON-162291
  Scenario: Modify a shared view with groups
    Given a shared custom view with a group
    When an user of this group is using the shared view
    Then he can modify the content of the shared view

  @TEST_MON-162292
  Scenario: Remove an unlocked shared view with groups
    Given a configured shared custom view with a group
    And an user of this group is using the configured shared view
    When he removes the shared view
    Then the view is not visible anymore
    And the user can use the shared view again

  @TEST_MON-162293
  Scenario: Modify an unlocked shared view with groups and applies changes
    Given a configured shared custom view with a group
    And an user of this group is using the configured shared view
    When the user modifies the custom view
    Then the changes are reflected on all users displaying the custom view

  @TEST_MON-162294
  Scenario: Deletion of an unlocked shared view with groups
    Given a configured shared custom view with a group
    And an user of this group is using the configured shared view
    When the owner removes the view
    Then the view remains visible for all users displaying the custom view
    And the view is removed for the owner