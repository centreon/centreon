Feature: Stored secrets
  As a Centreon administrator
  I want the secrets stored in the configuration never to be displayed or exposed
  So that reading a form, the API or the generated files does not leak a password

  @MON-214331 @system
  Scenario: Stored secrets are masked in the UI and the API and stored encrypted in the generated files
    Given the host rbt-secret-host has an SNMP community and a custom macro flagged as password
    When the host is reopened in the legacy host form
    Then both values are masked and absent from the page source
    When the host is read through the API v2 configuration endpoint
    Then the password macro value is null
    When the configuration is generated
    Then the generated files hold both secrets encrypted and never in clear text
