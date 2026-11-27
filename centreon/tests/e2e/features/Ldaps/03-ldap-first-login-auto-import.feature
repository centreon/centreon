Feature: LDAP first login auto import
  As a company administrator
  I want an LDAP user to be created in Centreon at its first login
  So that I do not have to import each user by hand

  @MON-214320
  Scenario: An LDAP user not yet imported is created at first login with the contact template and the contact groups of the LDAP configuration
    Given an LDAP configuration with Auto import users enabled, a contact template and a default contact group
    And a contact group named after one LDAP group of the user
    And the LDAP user has never logged in to Centreon
    When the LDAP user logs in with its directory password
    Then the login succeeds
    And a contact of authentication type LDAP is created from the contact template
    And the contact is linked to the default contact group and to the contact group named after its LDAP group
