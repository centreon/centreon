@ignore
@REQ_MON-69140
Feature: Resource Access Management
  As an administrator, I want to add rules to limit or grant access for other Centreon users.
  As a simple Centreon user, I should see only what the administrator has allowed me to access.

  Background:
    Given I am logged in as a user with limited access
    And I have restricted visibility to resources
    Then I am logged out after setup

  Scenario: Adding access rule for one Host resources
    Given an Administrator is logged into the platform
    When the Administrator is redirected to Administration > ACL > Resource Access Management
    Then the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    When the Administrator selects a simple user from the contacts and clicks "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user is redirected to Monitoring > Resources
    Then the user can see the Host selected by the Administrator

  Scenario: Adding access rule for one Business view resources
    Given an Administrator is logged into the platform
    When the Administrator is redirected to Administration > ACL > Resource Access Management
    Then the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Business view" as the resource and fills in the required fields
    When the Administrator selects a simple user from the contacts and clicks "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user is redirected to Monitoring > business activity > Monitoring
    Then the user can access the selected business view

  Scenario: Adding access rule for all Host groups
    Given an Administrator is logged into the platform
    When the Administrator is redirected to Administration > ACL > Resource Access Management
    Then the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    And the Administrator selects "All hosts"
    When the Administrator selects a simple user from the contacts and clicks "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user is redirected to Monitoring > Resources
    Then the user can see all Hosts

  Scenario: Adding access rule for all Business views
    Given an Administrator is logged into the platform
    When the Administrator is redirected to Administration > ACL > Resource Access Management
    Then the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Business view" as the resource and fills in the required fields
    And the Administrator selects "All Buiness views"
    When the Administrator selects a simple user from the contacts and clicks "Save"
    Then the Administrator logs out
    Given the selected user is logged in
    When the user is redirected to Monitoring > business activity > Monitoring
    Then the user can access all the business views

  Scenario: Adding access rule for all Contacts
    Given an Administrator is logged into the platform
    When the Administrator is redirected to Administration > ACL > Resource Access Management
    Then the Administrator clicks on the "Add" button
    When the form is displayed
    Then the Administrator selects "Host" as the resource and fills in the required fields
    And the Administrator selects "All contacts" and clicks "Save"
    Given a new user is created
    Then the Administrator logs out
    When the user just created is logged in
    And the user is redirected to Monitoring > Resources
    Then the user can see the Host selected by the Administrator
