Feature: Broker database connection over TLS
  As a Centreon administrator
  I want the Broker to open its database sessions over TLS when TLS is enabled on the database connection
  So that the Broker never falls back to clear text after a configuration export

  # Needs the database TLS stack: docker-compose.yml with the
  # docker-compose.tls.yml overlay (certificates, database offering TLS) and
  # performance_schema enabled on the database to read the session TLS state.
  # cy.startContainers takes a single compose file and the overlay moves the
  # web UI behind an HTTP to HTTPS redirect: not runnable in the CI stack yet.
  @MON-214863 @system @ignore
  Scenario: Broker connects to the database over TLS after a configuration export
    Given an administrator is logged in
    And TLS is enabled on the central database connection
    When the administrator exports the configuration of the central and restarts Broker
    Then the export ends with no error
    And the Broker configuration has the database TLS options enabled
    And all Broker sessions on the database use TLS
    And the Broker logs show no database connection error
