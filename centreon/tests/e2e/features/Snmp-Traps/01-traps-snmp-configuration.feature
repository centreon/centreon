Feature: trapsSnmpConfiguration
  As an IT supervisor
  I want to configure SNMP traps
  To monitore a router

  Background:
    Given a user is logged in Centreon

  @MON-200041
  Scenario: The SNMP traps listing loads through the AJAX framework
    Given an SNMP trap definition is configured
    When the user opens the SNMP traps listing
    Then the AJAX listing table is displayed with the configured trap

  @MON-200041
  Scenario: The search filters the SNMP traps by name
    Given two SNMP trap definitions are configured
    When the user opens the SNMP traps listing
    And the user searches for the first trap
    Then only the matching trap is displayed

  @MON-200041
  Scenario: The listing shows pagination information
    Given an SNMP trap definition is configured
    When the user opens the SNMP traps listing
    Then the pagination information shows the total count

  @MON-200041
  Scenario: The search term persists across navigation
    Given two SNMP trap definitions are configured
    When the user opens the SNMP traps listing
    And the user searches for the first trap
    And the user opens the trap form and comes back to the listing
    Then the search field still contains the search term

  @MON-151632
  Scenario: Creating SNMP trap with advanced matching rule
    When the user adds a new SNMP trap definition with an advanced matching rule
    Then the trap definition is saved with its properties, especially the content of Regexp field

  @MON-151633
  Scenario: Modify SNMP trap definition
    When the user modifies some properties of an existing SNMP trap definition
    Then all changes are saved

  @MON-151634
  Scenario: Duplicate SNMP trap definition
    When the user has duplicated one existing SNMP trap definition
    Then all SNMP trap properties are unchanged except the name

  @MON-151635
  Scenario: Delete SNMP trap definition
    When the user has deleted one existing SNMP trap definition
    Then this definition disappears from the SNMP trap list
