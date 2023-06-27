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

  Scenario: Dashboard scenario with an ADMIN

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-admin;usr-admin;usr-admin@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-admin;reach_api;1
    ACLMENU;add;name-admin-ACLMENU;alias-admin-ACLMENU
    ACLMENU;grantrw;name-admin-ACLMENU;0;Home;Dashboard;Administrator;
    ACLGROUP;add;name-admin-ACLGROUP;alias-admin-ACLGROUP
    ACLGROUP;addmenu;name-admin-ACLGROUP;name-admin-ACLMENU
    ACLGROUP;setcontact;name-admin-ACLGROUP;usr-admin;
    """
    Given I am logged in with "usr-admin"/"Centreon@2023"
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
      | created_by.id   | 20               |
      | created_by.name | "usr-admin"      |
      | updated_by.id   | 20               |
      | updated_by.name | "usr-admin"      |
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
      | created_by.id   | 20               |
      | created_by.name | "usr-admin"      |
      | updated_by.id   | 20               |
      | updated_by.name | "usr-admin"      |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1                |
      | result[0].name            | "my-dashboard"   |
      | result[0].description     | "my-description" |
      | result[0].created_by.id   | 20               |
      | result[0].created_by.name | "usr-admin"      |
      | result[0].updated_by.id   | 20               |
      | result[0].updated_by.name | "usr-admin"      |
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

  Scenario: Dashboard scenario with a CREATOR

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboard;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;
    """
    Given I am logged in with "usr-creator"/"Centreon@2023"
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
      | created_by.id   | 20               |
      | created_by.name | "usr-creator"    |
      | updated_by.id   | 20               |
      | updated_by.name | "usr-creator"    |
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
      | created_by.id   | 20               |
      | created_by.name | "usr-creator"    |
      | updated_by.id   | 20               |
      | updated_by.name | "usr-creator"    |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1                |
      | result[0].name            | "my-dashboard"   |
      | result[0].description     | "my-description" |
      | result[0].created_by.id   | 20               |
      | result[0].created_by.name | "usr-creator"    |
      | result[0].updated_by.id   | 20               |
      | result[0].updated_by.name | "usr-creator"    |
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

  Scenario: Dashboard scenario with a VIEWER

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-admin;usr-admin;usr-admin@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-admin;reach_api;1
    ACLMENU;add;name-admin-ACLMENU;alias-admin-ACLMENU
    ACLMENU;grantrw;name-admin-ACLMENU;0;Home;Dashboard;Administrator;
    ACLGROUP;add;name-admin-ACLGROUP;alias-admin-ACLGROUP
    ACLGROUP;addmenu;name-admin-ACLGROUP;name-admin-ACLMENU
    ACLGROUP;setcontact;name-admin-ACLGROUP;usr-admin;

    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    ACLMENU;add;name-viewer-ACLMENU;alias-viewer-ACLMENU
    ACLMENU;grantrw;name-viewer-ACLMENU;0;Home;Dashboard;Viewer;
    ACLGROUP;add;name-viewer-ACLGROUP;alias-viewer-ACLGROUP
    ACLGROUP;addmenu;name-viewer-ACLGROUP;name-viewer-ACLMENU
    ACLGROUP;setcontact;name-viewer-ACLGROUP;usr-viewer;
    """
    Given I am logged in with "usr-admin"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 3

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "200"

    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "403"

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    When I send a GET request to '/api/latest/configuration/dashboards/999'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/1' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "404"

  Scenario: Dashboard scenario with a VIEWER + CREATOR + ADMIN

    # Create all the users
    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-admin;usr-admin;usr-admin@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-admin;reach_api;1
    ACLMENU;add;name-admin-ACLMENU;alias-admin-ACLMENU
    ACLMENU;grantrw;name-admin-ACLMENU;0;Home;Dashboard;Administrator;
    ACLGROUP;add;name-admin-ACLGROUP;alias-admin-ACLGROUP
    ACLGROUP;addmenu;name-admin-ACLGROUP;name-admin-ACLMENU
    ACLGROUP;setcontact;name-admin-ACLGROUP;usr-admin;

    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboard;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;

    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    ACLMENU;add;name-viewer-ACLMENU;alias-viewer-ACLMENU
    ACLMENU;grantrw;name-viewer-ACLMENU;0;Home;Dashboard;Viewer;
    ACLGROUP;add;name-viewer-ACLGROUP;alias-viewer-ACLGROUP
    ACLGROUP;addmenu;name-viewer-ACLGROUP;name-viewer-ACLMENU
    ACLGROUP;setcontact;name-viewer-ACLGROUP;usr-viewer;
    """
    And a feature flag "dashboard" of bitmask 3

    #---------- as ADMIN ----------#
    Given I am logged in with "usr-admin"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "admin-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name             | path |
      | adminDashboardId | id   |

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    #---------- as CREATOR ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "creator-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name               | path |
      | creatorDashboardId | id   |

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    # The creator cannot GET, PATCH, DELETE the ADMIN dashboard not shared to him.

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "404"

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    # The VIEWER cannot GET, PATCH, DELETE the ADMIN dashboard not shared to him.

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "404"

    # The VIEWER cannot GET, PATCH, DELETE the CREATOR dashboard not shared to him.

    When I send a GET request to '/api/latest/configuration/dashboards/<creatorDashboardId>'
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<creatorDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<creatorDashboardId>'
    Then the response code should be "404"

    #---------- as ADMIN ----------#
    Given I am logged in with "usr-admin"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 2 elements

    # The ADMIN can GET, PATCH, DELETE the CREATOR dashboard not shared to him.

    When I send a GET request to '/api/latest/configuration/dashboards/<creatorDashboardId>'
    Then the response code should be "200"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<creatorDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<creatorDashboardId>'
    Then the response code should be "204"
