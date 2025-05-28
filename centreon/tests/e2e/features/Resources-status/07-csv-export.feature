Feature: Submit a Result to a Passive service or host
  As a Centreon user
  I want to force the status and output of a passive service or host
  To launch a specific event

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-158565
  Scenario: Submit result to a passive service
    Given one passive service has been configured using arguments status and output exists
    When the user submits some results to this service
    Then the values are set as wanted in Monitoring > Status details page of this service

  @TEST_MON-158566
  Scenario: Submit result to a passive host
    Given one passive host has been configured using arguments status and output exists
    When the user submits some results to this host
    Then the values are set as wanted in Monitoring > Status details page of this host