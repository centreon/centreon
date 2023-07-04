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
    And the header "location" should match "!/configuration/dashboards/1$!"

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

  Scenario: Dashboard contact access rights CRUD with an ADMIN

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
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [{
         "id": 20,
         "name": "usr-admin",
         "email": "usr-admin@centreon.test",
         "role": "editor"
      }],
      "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 1 }
    }
    """

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts' with body:
    """
    { "id": 1, "role": "viewer" }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
      "id": 1,
      "name": "admin admin",
      "email": "admin@centreon.com",
      "role": "viewer"
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1          |
      | result[0].role            | "viewer"   |
      | result[1].id              | 20         |
      | result[1].role            | "editor"   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1          |
      | result[0].role            | "editor"   |
      | result[1].id              | 20         |
      | result[1].role            | "editor"   |

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 20         |
      | result[0].role            | "editor"   |

  Scenario: Dashboard contact access rights CRUD with a CREATOR

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
    And a feature flag "dashboard" of bitmask 3

    #---------- as ADMIN ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [{
         "id": 20,
         "name": "usr-creator",
         "email": "usr-creator@centreon.test",
         "role": "editor"
      }],
      "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 1 }
    }
    """

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts' with body:
    """
    { "id": 1, "role": "viewer" }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
      "id": 1,
      "name": "admin admin",
      "email": "admin@centreon.com",
      "role": "viewer"
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1          |
      | result[0].role            | "viewer"   |
      | result[1].id              | 20         |
      | result[1].role            | "editor"   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 1          |
      | result[0].role            | "editor"   |
      | result[1].id              | 20         |
      | result[1].role            | "editor"   |

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts/1'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 20         |
      | result[0].role            | "editor"   |

  Scenario: Dashboard contact access rights CRUD with a VIEWER

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

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts' with body:
    """
    { "id": 1, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts'
    Then the response code should be "200"
    And the json node "result" should have 2 elements

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts'
    Then the response code should be "404"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts' with body:
    """
    { "id": 20, "role": "editor" }
    """
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts/1'
    Then the response code should be "404"

  Scenario: Dashboard access rights ADMIN sharing contact as "viewer" with a CREATOR + VIEWER

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

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts' with body:
    """
    { "id": 21, "role": "viewer" }
    """
    Then the response code should be "201"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts' with body:
    """
    { "id": 22, "role": "viewer" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contacts'
    And the json node "result" should have 3 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 20         |
      | result[0].role            | "editor"   |
      | result[1].id              | 21         |
      | result[1].role            | "viewer"   |
      | result[2].id              | 22         |
      | result[2].role            | "viewer"   |

    #---------- as CREATOR ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard"     |
      | own_role          | "viewer"              |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "403"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "403"

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard"     |
      | own_role          | "viewer"              |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "403"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "403"

  Scenario: Dashboard access rights ADMIN sharing contact as "editor" with a CREATOR + VIEWER

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
    { "name": "admin-dashboard1" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name              | path |
      | adminDashboardId1 | id   |

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "admin-dashboard2" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name              | path |
      | adminDashboardId2 | id   |

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 2 elements

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId1>/access_rights/contacts' with body:
    """
    { "id": 21, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId2>/access_rights/contacts' with body:
    """
    { "id": 22, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId1>/access_rights/contacts'
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 20         |
      | result[0].role            | "editor"   |
      | result[1].id              | 21         |
      | result[1].role            | "editor"   |

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId2>/access_rights/contacts'
    And the json node "result" should have 2 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 20         |
      | result[0].role            | "editor"   |
      | result[1].id              | 22         |
      | result[1].role            | "editor"   |

    #---------- as CREATOR ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId1>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard1"     |
      | own_role          | "editor"               |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId1>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId1>'
    Then the response code should be "204"

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId2>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard2"     |
      | own_role          | "editor"               |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId2>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId2>'
    Then the response code should be "204"

  Scenario: Dashboard contact group access rights CRUD with an ADMIN

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
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/1'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [],
      "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 0 }
    }
    """

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 3, "role": "viewer" }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
      "id": 3,
      "name": "Guest",
      "role": "viewer"
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 3          |
      | result[0].role            | "viewer"   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/3' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 3          |
      | result[0].role            | "editor"   |

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/3'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Dashboard contact group access rights CRUD with a CREATOR

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
    And a feature flag "dashboard" of bitmask 3

    #---------- as ADMIN ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/1' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/1'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [],
      "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 0 }
    }
    """

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 3, "role": "viewer" }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
      "id": 3,
      "name": "Guest",
      "role": "viewer"
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 3          |
      | result[0].role            | "viewer"   |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/3' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
    And the JSON nodes should be equal to:
      | result[0].id              | 3          |
      | result[0].role            | "editor"   |

    When I send a DELETE request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups/3'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Dashboard contact group access rights CRUD with a VIEWER

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

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 3, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups'
    Then the response code should be "404"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 20, "role": "editor" }
    """
    Then the response code should be "404"

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups/3' with body:
    """
    { "role": "editor" }
    """
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups/3'
    Then the response code should be "404"

  Scenario: Dashboard access rights ADMIN sharing contact group as "viewer" with a CREATOR + VIEWER

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
    CG;add;name-admin-CG;alias-admin-CG;
    CG;addcontact;name-admin-CG;usr-admin;

    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboard;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;
    CG;add;name-creator-CG;alias-creator-CG;
    CG;addcontact;name-creator-CG;usr-creator;

    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    ACLMENU;add;name-viewer-ACLMENU;alias-viewer-ACLMENU
    ACLMENU;grantrw;name-viewer-ACLMENU;0;Home;Dashboard;Viewer;
    ACLGROUP;add;name-viewer-ACLGROUP;alias-viewer-ACLGROUP
    ACLGROUP;addmenu;name-viewer-ACLGROUP;name-viewer-ACLMENU
    ACLGROUP;setcontact;name-viewer-ACLGROUP;usr-viewer;
    CG;add;name-viewer-CG;alias-viewer-CG;
    CG;addcontact;name-viewer-CG;usr-viewer;
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

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 7, "role": "viewer" }
    """
    Then the response code should be "201"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups' with body:
    """
    { "id": 8, "role": "viewer" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>/access_rights/contactgroups'
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 7,
                "name": "name-creator-CG",
                "role": "viewer"
            },
            {
                "id": 8,
                "name": "name-viewer-CG",
                "role": "viewer"
            }
        ],
        "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 2 }
    }
    """

    #---------- as CREATOR ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard"     |
      | own_role          | "viewer"              |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "403"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "403"

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard"     |
      | own_role          | "viewer"              |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "403"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId>'
    Then the response code should be "403"

  Scenario: Dashboard access rights ADMIN sharing contact group as "editor" with a CREATOR + VIEWER

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
    CG;add;name-admin-CG;alias-admin-CG;
    CG;addcontact;name-admin-CG;usr-admin;

    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboard;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;
    CG;add;name-creator-CG;alias-creator-CG;
    CG;addcontact;name-creator-CG;usr-creator;

    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    ACLMENU;add;name-viewer-ACLMENU;alias-viewer-ACLMENU
    ACLMENU;grantrw;name-viewer-ACLMENU;0;Home;Dashboard;Viewer;
    ACLGROUP;add;name-viewer-ACLGROUP;alias-viewer-ACLGROUP
    ACLGROUP;addmenu;name-viewer-ACLGROUP;name-viewer-ACLMENU
    ACLGROUP;setcontact;name-viewer-ACLGROUP;usr-viewer;
    CG;add;name-viewer-CG;alias-viewer-CG;
    CG;addcontact;name-viewer-CG;usr-viewer;
    """
    And a feature flag "dashboard" of bitmask 3

    #---------- as ADMIN ----------#
    Given I am logged in with "usr-admin"/"Centreon@2023"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "admin-dashboard1" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name              | path |
      | adminDashboardId1 | id   |

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "admin-dashboard2" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name              | path |
      | adminDashboardId2 | id   |

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the json node "result" should have 2 elements

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId1>/access_rights/contactgroups' with body:
    """
    { "id": 7, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a POST request to '/api/latest/configuration/dashboards/<adminDashboardId2>/access_rights/contactgroups' with body:
    """
    { "id": 8, "role": "editor" }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId1>/access_rights/contactgroups'
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 7,
                "name": "name-creator-CG",
                "role": "editor"
            }
        ],
        "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 1 }
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId2>/access_rights/contactgroups'
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 8,
                "name": "name-viewer-CG",
                "role": "editor"
            }
        ],
        "meta": { "page": 1, "limit": 10, "search": {}, "sort_by": {}, "total": 1 }
    }
    """

    #---------- as CREATOR ----------#
    Given I am logged in with "usr-creator"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId1>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard1"     |
      | own_role          | "editor"               |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId1>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId1>'
    Then the response code should be "204"

    #---------- as VIEWER ----------#
    Given I am logged in with "usr-viewer"/"Centreon@2023"

    When I send a GET request to '/api/latest/configuration/dashboards/<adminDashboardId2>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name              | "admin-dashboard2"     |
      | own_role          | "editor"               |

    When I send a PATCH request to '/api/latest/configuration/dashboards/<adminDashboardId2>' with body:
    """
    { "name": "modified-name" }
    """
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/dashboards/<adminDashboardId2>'
    Then the response code should be "204"

  Scenario: Dashboard search contacts + contact groups with an allowed user as VIEWER

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    ACLMENU;add;name-viewer-ACLMENU;alias-viewer-ACLMENU
    ACLMENU;grantrw;name-viewer-ACLMENU;0;Home;Dashboard;Viewer;
    ACLGROUP;add;name-viewer-ACLGROUP;alias-viewer-ACLGROUP
    ACLGROUP;addmenu;name-viewer-ACLGROUP;name-viewer-ACLMENU
    ACLGROUP;setcontact;name-viewer-ACLGROUP;usr-viewer;
    """
    Given I am logged in with "usr-viewer"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 3

    When I send a GET request to '/api/latest/configuration/dashboards/contacts?search={"name":{"$lk":"%25admin%25"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "admin admin"
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": { "$and": { "name": { "$lk": "%admin%" } } },
            "sort_by": {},
            "total": 1
        }
    }
    """

    When I send a GET request to '/api/latest/configuration/dashboards/contactgroups?search={"name":{"$lk":"%25guest%25"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 3,
                "name": "Guest"
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": { "$and": { "name": { "$lk": "%guest%" } } },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Dashboard search contacts + contact groups with a NOT allowed user

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    """
    Given I am logged in with "usr-viewer"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 3

    When I send a GET request to '/api/latest/configuration/dashboards/contacts'
    Then the response code should be "403"

    When I send a GET request to '/api/latest/configuration/dashboards/contactgroups'
    Then the response code should be "403"

  Scenario: Dashboard search contacts + contact groups endpoints not enabled

    Given the following CLAPI import data:
    """
    CONTACT;ADD;usr-viewer;usr-viewer;usr-viewer@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-viewer;reach_api;1
    """
    Given I am logged in with "usr-viewer"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 0

    When I send a GET request to '/api/latest/configuration/dashboards/contacts'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/contactgroups'
    Then the response code should be "404"
