@REQ_MON-151631
Feature: TrapsSNMPConfiguration
  As an IT supervisor
  I want to configure SNMP traps
  To monitore a router

  Background:
    Given a user is logged in Centreon

  @TEST_MON-151632
  Scenario: Creating SNMP trap with advanced matching rule
    When the user adds a new SNMP trap definition with an advanced matching rule
    Then the trap definition is saved with its properties, especially the content of Regexp field

  @TEST_MON-151633
  Scenario: Modify SNMP trap definition
    When the user modifies some properties of an existing SNMP trap definition
    Then all changes are saved

  @TEST_MON-151634
  Scenario: Duplicate SNMP trap definition
    When the user has duplicated one existing SNMP trap definition
    Then all SNMP trap properties are updated

  @TEST_MON-151635
  Scenario: Delete SNMP trap definition
    When the user has deleted one existing SNMP trap definition
    Then this definition disappears from the SNMP trap list
