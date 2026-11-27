Feature: Host names with special characters through the API
  As a configuration operator
  I want host names with printable special characters to be accepted and control characters to be refused
  So that the generated monitoring configuration holds only the objects I created

  @MON-215103 @system
  Scenario: Create and update a host with special characters in its name through the API and generate the configuration
    Given a non-admin configuration operator is logged in to the API
    And a poller is available to monitor hosts
    When the operator creates a host whose name contains printable special characters
    Then the host is created with that name
    When the operator updates the host name with other printable special characters
    Then the host shows the updated name
    When the operator creates a host whose name contains a line break
    Then the creation is refused with a validation error
    When the operator updates the host name with a name containing a tab character
    Then the update is refused with a validation error
    And the host keeps its previous name
    When the operator generates and reloads the configuration of the poller
    Then the generation ends without error
    And the generated configuration contains the host once under its updated name
    And the generated configuration contains no object created from a host name
