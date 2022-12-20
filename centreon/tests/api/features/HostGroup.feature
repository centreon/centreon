Feature:
  In order to monitor hosts by groups
  As a user
  I want to get host group information using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host group listing with an Administrator
    Given I am logged in
    And the following CLAPI import data:
    """
    HG;ADD;Test Host Group;Alias Test host group
    """

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": {"$eq": "Test Host Group"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 62,
                "name": "Test Host Group",
                "alias": "Alias Test host group",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$eq": "Test Host Group"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Host group listing with an Administrator and a disabled host group
    Given I am logged in
    And the following CLAPI import data:
    """
    HG;ADD;Test Host disabled;Alias Test host group
    HG;setparam;Test Host disabled;activate;0
    """

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"is_activated": false}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 62,
                "name": "Test Host disabled",
                "alias": "Alias Test host group",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
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

  Scenario: Host group listing with a READ user
    Given the following CLAPI import data:
    """
    HG;ADD;host-group1;host-group1-alias
    HG;ADD;host-group2;host-group2-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;0;Configuration;Hosts;
    ACLMENU;grantro;ACL Menu test;0;Configuration;Hosts;Host Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group1
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group2
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": {"$lk": "host-group%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 62,
                "name": "host-group1",
                "alias": "host-group1-alias",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            },
            {
                "id": 63,
                "name": "host-group2",
                "alias": "host-group2-alias",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$lk": "host-group%"}}
            },
            "sort_by": {},
            "total": 2
        }
    }
    """


  Scenario: Host group listing with a READ_WRITE user
    Given the following CLAPI import data:
    """
    HG;ADD;host-group1;host-group1-alias
    HG;ADD;host-group2;host-group2-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;Host Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group1
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group2
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": {"$lk": "host-group%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 62,
                "name": "host-group1",
                "alias": "host-group1-alias",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            },
            {
                "id": 63,
                "name": "host-group2",
                "alias": "host-group2-alias",
                "notes": null,
                "notes_url": null,
                "action_url": null,
                "icon_id": null,
                "icon_map_id": null,
                "rrd": null,
                "geo_coords": null,
                "comment": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$lk": "host-group%"}}
            },
            "sort_by": {},
            "total": 2
        }
    }
    """

  Scenario: Host group deletion with an Administrator
    Given I am logged in
    And the following CLAPI import data:
    """
    HG;ADD;host-group1;host-group1-alias
    """

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": "host-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/hosts/groups/62'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": "host-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements


  Scenario: Host group deletion with a READ user forbidden
    Given the following CLAPI import data:
    """
    HG;ADD;host-group1;host-group1-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;0;Configuration;Hosts;
    ACLMENU;grantro;ACL Menu test;0;Configuration;Hosts;Host Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group1
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": "host-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/hosts/groups/62'
    Then the response code should be "403"


  Scenario: Host group deletion with a READ_WRITE user allowed
    Given the following CLAPI import data:
    """
    HG;ADD;host-group1;host-group1-alias
    CONTACT;ADD;abu;abu;abu@centreon.test;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;abu;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;
    ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;Host Groups;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;host-group1
    ACLGROUP;add;ACL Group test;ACL Group test alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;setcontact;ACL Group test;abu;
    """
    And I am logged in with "abu"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": "host-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/hosts/groups/62'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": "host-group1"}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements
