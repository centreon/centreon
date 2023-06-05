Feature:
  In order to use dashboards
  As a user
  I want to get and set dashboard information using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Dashboard with orphan owner return null in API
    Given I am logged in
    And a feature flag "dashboard" of bitmask 3

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr1;usr1;usr1@centreon.test;Centreon@2022;1;1;en_US;local
    CONTACT;ADD;usr2;usr2;usr2@centreon.test;Centreon@2022;1;1;en_US;local
    """
    And I am logged in with "usr1"/"Centreon@2022"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | id   | 1              |
      | name | "my-dashboard" |

    When I send a GET request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | created_by.id   | 20     |
      | created_by.name | "usr1" |
      | updated_by.id   | 20     |
      | updated_by.name | "usr1" |

    Given the following CLAPI import data:
    """
    CONTACT;DEL;usr1
    """
    And I am logged in with "usr2"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | created_by | null |
      | updated_by | null |

  Scenario: Dashboard endpoints do NOT found because of disabled feature
    Given I am logged in
    And a feature flag "dashboard" of bitmask 0

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/1' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "404"

  Scenario: Dashboard add + get with an Administrator
    Given I am logged in
    And a feature flag "dashboard" of bitmask 3
    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    {
       "name": "my-dashboard",
       "description": "my-description"
    }
    """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | id              | 1                |
      | name            | "my-dashboard"   |
      | description     | "my-description" |
      | created_by.id   | 1                |
      | created_by.name | "admin admin"    |
      | updated_by.id   | 1                |
      | updated_by.name | "admin admin"    |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | id              | 1                |
      | name            | "my-dashboard"   |
      | description     | "my-description" |
      | created_by.id   | 1                |
      | created_by.name | "admin admin"    |
      | updated_by.id   | 1                |
      | updated_by.name | "admin admin"    |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | result[0].id              | 1                |
      | result[0].name            | "my-dashboard"   |
      | result[0].description     | "my-description" |
      | result[0].created_by.id   | 1                |
      | result[0].created_by.name | "admin admin"    |
      | result[0].updated_by.id   | 1                |
      | result[0].updated_by.name | "admin admin"    |
    And the JSON node "result[0].created_at" should exist
    And the JSON node "result[0].updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards/999'
    Then the response code should be "404"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "2nd-dashboard" }
    """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | id          | 2               |
      | name        | "2nd-dashboard" |
      | description | null            |

    When I send a DELETE request to '/api/latest/configuration/dashboards/999'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/2'
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    { "description": "modified-description" }
    """
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    {
      "panels": [
        {
          "name": "added-panel-1",
          "layout": { "x": 1, "y": 2, "width": 3, "height": 4, "min_width": 5, "min_height": 6 },
          "widget_type": "widget-type",
          "widget_settings": { "foo": "bar", "number": 42 }
        },
        {
          "name": "added-panel-2",
          "layout": { "x": 1, "y": 2, "width": 3, "height": 4, "min_width": 5, "min_height": 6 },
          "widget_type": "widget-type",
          "widget_settings": { "foo": "bar", "number": 42 }
        }
      ]
    }
    """
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    {
      "panels": [
        {
          "id": 1,
          "name": "modified-panel-1",
          "layout": { "x": 1, "y": 2, "width": 3, "height": 4, "min_width": 5, "min_height": 6 },
          "widget_type": "widget-type",
          "widget_settings": { "foo": "bar", "number": 42 }
        },
        {
          "name": "added-panel-3",
          "layout": { "x": 1, "y": 2, "width": 3, "height": 4, "min_width": 5, "min_height": 6 },
          "widget_type": "widget-type",
          "widget_settings": { "foo": "bar", "number": 42 }
        }
      ]
    }
    """
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    { "unknown_field": "something" }
    """
    Then the response code should be "400"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name           | "modified-name"        |
      | description    | "modified-description" |
      | panels[0].id   | 1                      |
      | panels[0].name | "modified-panel-1"     |
      | panels[1].id   | 3                      |
      | panels[1].name | "added-panel-3"        |

    When I send a PATCH request to '/api/latest/configuration/dashboards/999' with body:
    """
    { "name": "any-name" }
    """
    Then the response code should be "404"
