Feature:
  In order to check the notifications
  As a logged in user
  I want to manipulate notification using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Notification creation as admin
    Given I am logged in
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;;0;0;;local
    """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": ["event_services": 2]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [1,2]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message"
          }
        ],
        "users": [20,21],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "name": "notification-name",
        "timeperiod": ["id": 1, "name": "24x7"],
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [
              ["id":53, "name": "Linux-Servers"],
              ["id":56, "name": "Printers"]
            ],
            "extra": ["event_services": 2]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [
              ["id":1, "name": "service-grp1"],
              ["id":2, "name": "service-grp2"]
            ]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message"
          }
        ],
        "users": [
          ["id":20, "name": "user-name1"],
          ["id":21, "name": "user-name2"]
        ],
        "is_activated": true
      }
      """

  Scenario: Notification creation as non-admin
    Given the following CLAPI import data:
    """
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Notifications;Notifications
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;;0;0;;local
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": ["event_services": 2]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [1,2]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message"
          }
        ],
        "users": [20,21],
        "is_activated": true
      }
      """
    Then the response code should be "409"

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": ["event_services": 2]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [1]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message"
          }
        ],
        "users": [20,21],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "name": "notification-name",
        "timeperiod": ["id": 1, "name": "24x7"],
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [
              ["id":53, "name": "Linux-Servers"],
              ["id":56, "name": "Printers"]
            ],
            "extra": ["event_services": 2]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [
              ["id":1, "name": "service-grp1"]
            ]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message"
          }
        ],
        "users": [
          ["id":20, "name": "user-name1"],
          ["id":21, "name": "user-name2"]
        ],
        "is_activated": true
      }
      """


  Scenario: Create a service category
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "   service-cat-name   ",
        "alias": "   service-cat-alias   "
        }
        """
    Then the response code should be "201"
    And the JSON should be equal to:
        """
        {
        "id": 5,
        "name": "service-cat-name",
        "alias": "service-cat-alias",
        "is_activated": true
        }
        """
    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "service-cat-name",
        "alias": "service-cat-alias"
        }
        """
    Then the response code should be "409"

  Scenario: Create service category with an invalid payload
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "not_existing": "Hello World"
        }
        """
    Then the response code should be "400"

    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "",
        "alias": "service-cat-alias"
        }
        """
    Then the response code should be "400"
