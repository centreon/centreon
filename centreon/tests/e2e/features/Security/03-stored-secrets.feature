Feature: Stored secrets
  As a Centreon administrator
  I want the secrets stored in the configuration never to be displayed or exposed
  So that reading a form, the API or the generated files does not leak a password

  # Ignored until the expected permissions of the generated files are confirmed:
  # they are world-readable in the web image, with their secrets stored encrypted
  @MON-214331 @system @ignore
  Scenario: Stored secrets are masked in the UI and the API and generated files are not readable by other users
    Given the host rbt-secret-host has an SNMP community and a custom macro flagged as password
    When the host is reopened in the legacy host form
    Then both values are masked and absent from the page source
    When the host is read through the API v2 configuration endpoint
    Then the password macro value is null
    When the configuration is generated
    Then the generated files and the Centreon configuration files are not readable by other users
