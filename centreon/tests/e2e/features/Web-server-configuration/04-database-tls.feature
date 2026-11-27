Feature: Database connection of the web application over TLS
  As a Centreon administrator
  I want the web application to reach its database over TLS once the TLS options are set
  So that the database traffic of the web interface and the API is never sent in clear text

  # Not run in the CI stack: cy.startContainers() starts the base compose file only,
  # whose database runs with --skip-ssl. The TLS overlay (docker-compose.tls.yml,
  # certificates from certgen) cannot be loaded by cy.startContainers().
  @MON-215160 @system @ignore
  Scenario: Web application connects to its database over TLS when the TLS options are set in the .env file
    Given a platform whose database accepts TLS connections
    Given the database TLS options are set in the web application's .env file
    And the web services are restarted
    When the administrator logs in to the web interface and calls the API
    Then the web application sessions on the database use TLS
    And the login still succeeds when the database requires secure transport
