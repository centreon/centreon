@REQ_MON-69140
Feature: Resource Access Management
  As an administrator, I want to add rules to limit or grant access for other Centreon users.
  As a simple Centreon user, I should see only what the administrator has allowed me to access.

  Background:
    Given I am logged in as a user with limited access
    And I have restricted visibility to resources

  Scenario: Adding access rule for one Host resources
    Given an Administrator is logged in on the platform
    When new hosts are created
    Then the Administrator navigates to the "Resource Access Management" page
    And the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    When the Administrator selects a simple user from the contacts and clicks on "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user navigates to the monitoring "Resources" page
    Then the user can see the Host selected by the Administrator

  Scenario: Adding access rule for all hosts
    Given an Administrator is logged in on the platform
    When new hosts are created
    Then the Administrator navigates to the "Resource Access Management" page
    And the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    And the Administrator selects "All hosts"
    When the Administrator selects a simple user from the contacts and clicks on "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user navigates to the monitoring "Resources" page
    Then the user can see all hosts

  Scenario: Adding access rule for all Contacts
    Given an Administrator is logged in on the platform
    When new hosts are created
    Then the Administrator navigates to the "Resource Access Management" page
    And the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    And the Administrator selects "All contacts" and clicks on "Save"
    Given a new user is created
    When the Administrator logs out
    And the user that was just created is logged in
    Then the user navigates to the monitoring "Resources" page
    And the user can see the Host selected by the Administrator
