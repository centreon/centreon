Feature:
  In order to monitor services by groups
  As a user
  I want to get service group information using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service group listing with an Administrator
    Given I am logged in
    And the following CLAPI import data:
    """
    SG;ADD;Test Service Group;Alias Test service group
    """

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": {"$eq": "Test Service Group"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "Test Service Group",
                "alias": "Alias Test service group",
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$eq": "Test Service Group"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service group listing with an Administrator and a disabled service group
    Given I am logged in
    And the following CLAPI import data:
    """
    SG;ADD;Test Service disabled;Alias Test service group
    SG;setparam;Test Service disabled;activate;0
    """

    When I send a GET request to '/api/latest/configuration/services/groups?search={"is_activated": false}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "Test Host disabled",
                "alias": "Alias Test host group",
                "geo_coords": null,
                "comment": null,
                "is_activated": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"is_activated": false}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service group listing with a READ user
    Given the following CLAPI import data:
    """
    HG;ADD;service-group1;service-group1-alias
    HG;ADD;service-group2;service-group2-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;0;Configuration;Services;
    ACLMENU;grantro;ACL Menu test;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group1
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group2
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": {"$lk": "service-group%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "host-group1",
                "alias": "host-group1-alias",
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            },
            {
                "id": 2,
                "name": "host-group2",
                "alias": "host-group2-alias",
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$lk": "service-group%"}}
            },
            "sort_by": {},
            "total": 2
        }
    }
    """

  Scenario: Service group listing with a READ_WRITE user
    Given the following CLAPI import data:
    """
    HG;ADD;service-group1;service-group1-alias
    HG;ADD;service-group2;service-group2-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group1
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group2
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": {"$lk": "service-group%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "host-group1",
                "alias": "host-group1-alias",
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            },
            {
                "id": 2,
                "name": "host-group2",
                "alias": "host-group2-alias",
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$lk": "service-group%"}}
            },
            "sort_by": {},
            "total": 2
        }
    }
    """

  Scenario: Service group deletion with an Administrator
    Given I am logged in
    And the following CLAPI import data:
    """
    HG;ADD;service-group1;service-group1-alias
    """

    When I send a GET request to '/api/latest/configuration/servcices/groups?search={"name": "service-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/groups/62'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": "service-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Service group deletion with a READ user forbidden
    Given the following CLAPI import data:
    """
    HG;ADD;service-group1;service-group1-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;0;Configuration;Services;
    ACLMENU;grantro;ACL Menu test;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group1
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": "service-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/groups/62'
    Then the response code should be "403"

  Scenario: Service group deletion with a READ_WRITE user allowed
    Given the following CLAPI import data:
    """
    HG;ADD;service-group1;service-group1-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-group1
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": "service-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/groups/62'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/groups?search={"name": "service-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Service group add with minimal payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    {
        "name": "test-add"
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 1,
        "name": "test-add",
        "alias": null,
        "geo_coords": null,
        "comment": null,
        "is_activated": true
    }
    """

  Scenario: Service group add with an invalid payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    {
        "not_existing": "Hello World"
    }
    """
    Then the response code should be "400"

  Scenario: Service group add with full payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    {
        "name": "test-add1",
        "alias": "test-alias",
        "geo_coords": "-2,+3",
        "comment": "test-comment",
        "is_activated": true
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 1,
        "name": "test-add1",
        "alias": "test-alias",
        "geo_coords": "-2,+3",
        "comment": "test-comment",
        "is_activated": true
    }
    """
    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    {
        "name": "test-add2",
        "alias": "",
        "geo_coords": "",
        "comment": "",
        "is_activated": true
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 2,
        "name": "test-add2",
        "alias": null,
        "geo_coords": null,
        "comment": null,
        "is_activated": true
    }
    """
    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    {"name": "test-add2"}
    """
    Then the response code should be "409"

  Scenario: Service group add with a READ user is forbidden
    Given the following CLAPI import data:
    """
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;name-ACLMENU;alias-ACLMENU
    ACLMENU;grantro;name-ACLMENU;0;Configuration;Services;
    ACLMENU;grantro;name-ACLMENU;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;name-ACLRESOURCE;name-ACLMENU-alias
    ACLGROUP;add;name-ACLGROUP;alias-ACLGROUP
    ACLGROUP;addmenu;name-ACLGROUP;name-ACLMENU
    ACLGROUP;addresource;name-ACLGROUP;name-ACLRESOURCE
    ACLGROUP;setcontact;name-ACLGROUP;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    { "name": "test-add" }
    """
    Then the response code should be "403"

  Scenario: Service group add with a READ_WRITE user is allowed
    Given the following CLAPI import data:
    """
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;name-ACLMENU;alias-ACLMENU
    ACLMENU;grantrw;name-ACLMENU;0;Configuration;Services;
    ACLMENU;grantrw;name-ACLMENU;0;Configuration;Services;Service Groups;
    ACLRESOURCE;add;name-ACLRESOURCE;name-ACLMENU-alias
    ACLGROUP;add;name-ACLGROUP;alias-ACLGROUP
    ACLGROUP;addmenu;name-ACLGROUP;name-ACLMENU
    ACLGROUP;addresource;name-ACLGROUP;name-ACLRESOURCE
    ACLGROUP;setcontact;name-ACLGROUP;abu;
        """
        And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/groups'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    When I send a POST request to '/api/latest/configuration/services/groups' with body:
    """
    { "name": "test-add" }
    """
    Then the response code should be "201"
    And the json node "name" should be equal to the string "test-add"

    When I send a GET request to '/api/latest/configuration/services/groups'
    Then the response code should be "200"
    And the json node "result" should have 1 elements
