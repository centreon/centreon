Feature: LDAPManualImport
  As a company administrator
  I want to import manually users
  In order to filter the ones who can access to Centreon Web

  Background:
    Given a user is logged in a Centreon server

  @TEST_MON-160613
  Scenario: Search and import one user whose alias contains an accent
    Given a LDAP configuration with Users auto import disabled has been created
    When the user searchs a specific user whose alias contains a special character such as an accent
    Then the LDAP search result displays the expected alias
    When the user imports the searched user
    Then the user is added to the contacts listing page

  @TEST_MON-160614
  Scenario: LDAP manually imported user can authenticate to Centreon Web
    Given one ldap user has been manually imported
    Then this user can log in to Centreon Web

  @TEST_MON-160851
  Scenario: User cannot change DN
    Given one ldap user has been manually imported
    And the ldap user has rights to access the contacts listing page
    Then this user can log in to Centreon Web
    When the ldap user goes to the contacts listing page
    Then the ldap user cannot update the contact dn
    And the ldap user cannot update the contact password