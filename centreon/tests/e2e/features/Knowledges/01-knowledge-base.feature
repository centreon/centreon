Feature: Knowledge Base
  As a Centreon user
  I want to link my hosts and services supervised to wiki procedures
  To have quickly additional information on my hosts and services

  Background:
    Given an admin user is logged in a Centreon server with MediaWiki installed

  @TEST_MON-161210
  Scenario: Check Host Knowledge
    Given a host is configured
    When the user adds a procedure concerning this host in MediaWiki
    Then a link towards this host procedure is available in the configuration

  @TEST_MON-161211
  Scenario: Check Service Knowledge
    Given a service is configured
    When the user adds a procedure concerning this service in MediaWiki
    Then a link towards this service procedure is available in configuration

  @TEST_MON-161212
  Scenario: Delete Knowledge Page
    Given the knowledge configuration page with procedure
    When the user deletes a wiki procedure
    Then the page is deleted and the option disappear