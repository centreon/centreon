Feature:
  In order to monitor a resource
  As a user
  I want to get resources by parent information using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Resource listing by parent
    Given I am logged in
    And a feature flag "resource_status_tree_view" of bitmask 3
    And the following CLAPI import data:
    """
    HOST;ADD;host_test;Test host;127.0.0.1;generic-host;central;
    SERVICE;ADD;host_test;service_ping;Ping-LAN
    HG;ADD;name-HG;alias-HG
    HG;ADDMEMBER;name-HG;host_test
    """
    And the configuration is generated and exported
    And I wait until host "host_test" is monitored
    And I wait until service "service_ping" from host "host_test" is monitored
    And I wait to get 1 result from "/api/monitoring/resources?search={"s.description":{"$rg":"^service_ping$"}}" (tries: 100)
    When I send a GET request to '/api/monitoring/resources/hosts?search={"s.description":{"$rg":"^service_ping$"}}'
    Then the response code should be "200"
    And the response should be formatted like JSON format "standard/listing.json"
    And the json node "result" should have 1 elements
    And the JSON node "result[0].name" should be equal to the string "host_test"
    And the JSON node "result[0].children.resources[0].resource_name" should be equal to the string "service_ping"
    And the JSON node "result[0].children.total" should be equal to the number 1
    And the JSON node "result[0].children.status_count.pending" should be equal to the number 1

    When I send a GET request to '/api/latest/monitoring/resources/hosts?hostgroup_names=["name-HG"]&sort_by={"name":"DESC"}'
    Then the response code should be "200"
    And the response should be formatted like JSON format "standard/listing.json"
    And the json node "result" should have 1 elements
    And the JSON node "result[0].name" should be equal to the string "host_test"
    And the JSON node "result[0].children.resources[0].resource_name" should be equal to the string "service_ping"
    And the JSON node "result[0].children.total" should be equal to the number 1
    And the JSON node "result[0].children.status_count.pending" should be equal to the number 1

    When I send a GET request to '/api/latest/monitoring/resources/hosts?types=["service"]&sort_by={"name":"DESC"}'
    Then the response code should be "200"
    And the response should be formatted like JSON format "standard/listing.json"
    And the json node "result" should have 2 elements
    And the JSON node "result[0].name" should be equal to the string "host_test"
    And the JSON node "result[1].name" should be equal to the string "Centreon-Server"
