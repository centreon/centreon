@REQ_MON-198741 @ignore
Feature: Web server on a non-default port
    As a Centreon admin whose UI is served on a non-default web server port
    I want a host configuration to be saved correctly
    So that the internal API call is not broken by the port being stripped

  # @ignore until: web container publishes the non-default port (MON-201337)
  # and the legacy form selectors are confirmed.
  @MON-201316
  Scenario: Saving a host works when the web server uses a non-default port
    Given a running platform served on a non-default web server port
    When the admin edits and saves a host through the legacy configuration form
    Then the host is saved without an internal API connection error
