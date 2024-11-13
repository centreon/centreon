Feature: LdapConfiguration
  As a company administrator
  I want to configure LDAP
  In order to easily administrate all logins to applications used in company

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-152401
  Scenario: Creating LDAP configuration
    When the user adds a new LDAP configuration
    Then the LDAP configuration is saved with its properties

  @TEST_MON-152402
  Scenario: Modify LDAP configuration
    Given one LDAP configuration has been created
    When the user modifies some properties of the existing LDAP configuration
    Then all changes are saved

  @TEST_MON-152403
  Scenario: Delete LDAP configuration
    Given one LDAP configuration has been created
    When the user has deleted the existing LDAP configuration
    Then this configuration has disappeared from the LDAP configuration list