Feature:
  In order to check the notifications
  As a logged in user
  I want to manipulate notification using api

  Background:
    Given a running cloud platform instance of Centreon Web API

  Scenario: Retrieve a notifiable Rule
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 2,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
        },
        {
          "type": "servicegroup",
          "events": 5,
          "ids": [1,2]
        }
      ],
      "messages": [
        {
          "channel": "Email",
          "subject": "Hello world !",
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3,5],
      "is_activated": true
    }
    """
    Then the response code should be "201"
    And I store response values in:
      | name           | path |
      | notificationId | id   |

    When I send a GET request to '/api/latest/configuration/notifications/<notificationId>/rules'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "notification_id": 1,
      "channels": {
        "email": {
          "subject": "Hello world !",
          "formatted_message": "a formatted message",
          "contacts": [
            {
              "email_address": "user1@mail.com",
              "full_name": "user-name1"
            },
            {
              "email_address": "user2@mail.com",
              "full_name": "user-name2"
            },
            {
              "email_address": "admin@centreon.com",
              "full_name": "admin admin"
            },
            {
              "email_address": "guest@localhost",
              "full_name": "Guest"
            },
            {
              "email_address": "user@localhost",
              "full_name": "User"
            }
          ]
        },
        "slack": null,
        "sms": null
      }
    }
    """

  Scenario: Notification creation as admin
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 2,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3,5],
      "is_activated": true
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
      "id": 1,
      "name": "notification-name",
      "timeperiod": {
          "id": 2,
          "name": "none"
      },
      "users": [
          {
              "id": 20,
              "name": "user-name1"
          },
          {
              "id": 21,
              "name": "user-name2"
          }
      ],
      "contactgroups": [
        {
          "id": 3,
          "name": "Guest"
        },
        {
          "id": 5,
          "name": "Supervisors"
        }
      ],
      "resources": [
          {
              "type": "hostgroup",
              "events": 5,
              "ids": [
                  {
                      "id": 53,
                      "name": "Linux-Servers"
                  },
                  {
                      "id": 56,
                      "name": "Printers"
                  }
              ],
              "extra": {
                  "event_services": 2
              }
          },
          {
              "type": "servicegroup",
              "events": 5,
              "ids": [
                  {
                      "id": 1,
                      "name": "service-grp1"
                  },
                  {
                      "id": 2,
                      "name": "service-grp2"
                  }
              ]
          }
      ],
      "messages": [
          {
              "channel": "Slack",
              "subject": "Hello world !",
              "message": "just a small message",
              "formatted_message": "a formatted message"
          }
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
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
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
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    CG;addcontact;Guest;ala;ala
    """
    And I am logged in with "ala"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [5],
        "is_activated": true
      }
      """
    Then the response code should be "400"

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [3],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 1,
        "name": "notification-name",
        "timeperiod": {
            "id": 2,
            "name": "none"
        },
        "users": [
            {
                "id": 20,
                "name": "ala"
            },
            {
                "id": 21,
                "name": "user-name1"
            }
        ],
        "contactgroups": [
          {
            "id": 3,
            "name": "Guest"
          }
        ],
        "resources": [
            {
                "type": "hostgroup",
                "events": 5,
                "ids": [
                    {
                        "id": 53,
                        "name": "Linux-Servers"
                    },
                    {
                        "id": 56,
                        "name": "Printers"
                    }
                ],
                "extra": {
                    "event_services": 2
                }
            },
            {
                "type": "servicegroup",
                "events": 5,
                "ids": [
                    {
                        "id": 1,
                        "name": "service-grp1"
                    }
                ]
            }
        ],
        "messages": [
            {
                "channel": "Slack",
                "subject": "Hello world !",
                "message": "just a small message",
                "formatted_message": "a formatted message"
            }
        ],
        "is_activated": true
      }
      """

  Scenario: Notification Listing as admin
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 1,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3,5],
      "is_activated": true
    }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/notifications'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [
        {
          "id": 1,
          "is_activated": true,
          "name": "notification-name",
          "user_count": 4,
          "channels": [
            "Slack"
          ],
          "resources": [
            {
              "type": "hostgroup",
              "count": 2
            },
            {
              "type": "servicegroup",
              "count": 2
            }
          ],
          "timeperiod": {
            "id": 1,
            "name": "24x7"
          }
        }
      ],
      "meta": {
        "page": 1,
        "limit": 10,
        "search": {},
        "sort_by": {},
        "total": 1
      }
    }
    """

  Scenario: Notification Listing as non-admin
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    CG;addcontact;Guest;test-user;test-user
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [3],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/notifications'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [
        {
          "id": 1,
          "is_activated": true,
          "name": "notification-name",
          "user_count": 3,
          "channels": [
            "Slack"
          ],
          "resources": [
            {
              "type": "hostgroup",
              "count": 2
            },
            {
              "type": "servicegroup",
              "count": 1
            }
          ],
          "timeperiod": {
            "id": 2,
            "name": "none"
          }
        }
      ],
      "meta": {
        "page": 1,
        "limit": 10,
        "search": {},
        "sort_by": {},
        "total": 1
      }
    }
    """

  Scenario: Notification listing as non-admin without sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [5],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    Given I am logged in with "test-user"/"Centreon@2022"
    When I send a GET request to '/api/latest/configuration/notifications'
    Then the response code should be "403"

  Scenario: Notification details as admin
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 1,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3,5],
      "is_activated": true
    }
    """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/configuration/notifications/1'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
      {
        "id": 1,
        "name": "notification-name",
        "timeperiod": {
            "id": 1,
            "name": "24x7"
        },
        "is_activated": true,
        "messages": [
            {
                "channel": "Slack",
                "subject": "Hello world !",
                "message": "just a small message",
                "formatted_message": "a formatted message"
            }
        ],
        "users": [
            {
                "id": 20,
                "name": "user-name1"
            },
            {
                "id": 21,
                "name": "user-name2"
            }
        ],
        "contactgroups": [
          {
            "id": 3,
            "name": "Guest"
          },
          {
            "id": 5,
            "name": "Supervisors"
          }
        ],
        "resources": [
            {
                "type": "hostgroup",
                "events": 5,
                "ids": [
                    {
                        "id": 53,
                        "name": "Linux-Servers"
                    },
                    {
                        "id": 56,
                        "name": "Printers"
                    }
                ],
                "extra": {
                    "event_services": 2
                }
            },
            {
                "type": "servicegroup",
                "events": 5,
                "ids": [
                    {
                        "id": 1,
                        "name": "service-grp1"
                    },
                    {
                        "id": 2,
                        "name": "service-grp2"
                    }
                ]
            }
        ]
      }
    """

  Scenario: Notification details as non-admin
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    CG;addcontact;Guest;test-user;test-user
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [3,5],
        "is_activated": false
      }
      """
    Then the response code should be "201"

    Given I am logged in with "test-user"/"Centreon@2022"
    When I send a GET request to '/api/latest/configuration/notifications/1'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "id": 1,
        "name": "notification-name",
        "timeperiod": {
            "id": 2,
            "name": "none"
        },
        "is_activated": false,
        "messages": [
            {
                "channel": "Slack",
                "subject": "Hello world !",
                "message": "just a small message",
                "formatted_message": "a formatted message"
            }
        ],
        "users": [
            {
                "id": 20,
                "name": "test-user"
            },
            {
                "id": 21,
                "name": "user-name1"
            }
        ],
        "contactgroups": [
            {
                "id": 3,
                "name": "Guest"
            }
        ],
        "resources": [
            {
                "type": "hostgroup",
                "events": 5,
                "ids": [
                    {
                        "id": 56,
                        "name": "Printers"
                    }
                ]
            },
            {
                "type": "servicegroup",
                "events": 5,
                "ids": [
                    {
                        "id": 1,
                        "name": "service-grp1"
                    }
                ]
            }
        ]
    }
    """

  Scenario: Notification details as non-admin without sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    Given I am logged in with "test-user"/"Centreon@2022"
    When I send a GET request to '/api/latest/configuration/notifications/1'
    Then the response code should be "403"

  Scenario: Notification details not found
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    When I send a GET request to '/api/latest/configuration/notifications/50'
    Then the response code should be "404"

  Scenario: Notification Update as admin
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 1,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3],
      "is_activated": true
    }
    """
    Then the response code should be "201"

    When I send a PUT request to '/api/latest/configuration/notifications/1' with body:
    """
    {
      "name": "notification-name-updated",
      "timeperiod_id": 1,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [3,5],
      "is_activated": true
    }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/notifications/1'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
          "id": 1,
          "name": "notification-name-updated",
          "timeperiod": {
              "id": 1,
              "name": "24x7"
          },
          "is_activated": true,
          "messages": [
              {
                  "channel": "Slack",
                  "subject": "Hello world !",
                  "message": "just a small message",
                  "formatted_message": "a formatted message"
              }
          ],
          "users": [
              {
                  "id": 20,
                  "name": "user-name1"
              },
              {
                  "id": 21,
                  "name": "user-name2"
              }
          ],
          "contactgroups": [
            {
              "id": 3,
              "name": "Guest"
            },
            {
              "id": 5,
              "name": "Supervisors"
            }
          ],
          "resources": [
              {
                  "type": "hostgroup",
                  "events": 5,
                  "ids": [
                      {
                          "id": 53,
                          "name": "Linux-Servers"
                      },
                      {
                          "id": 56,
                          "name": "Printers"
                      }
                  ],
                  "extra": {
                      "event_services": 2
                  }
              },
              {
                  "type": "servicegroup",
                  "events": 5,
                  "ids": [
                      {
                          "id": 1,
                          "name": "service-grp1"
                      },
                      {
                          "id": 2,
                          "name": "service-grp2"
                      }
                  ]
              }
          ]
      }
    """

  Scenario: Notification update as non-admin
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    CG;addcontact;Guest;test-user;test-user
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [2]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [3,5],
        "is_activated": false
      }
      """
    Then the response code should be "201"

    Given I am logged in with "test-user"/"Centreon@2022"
    When I send a PUT request to '/api/latest/configuration/notifications/1' with body:
    """
      {
        "name": "notification-name-updated",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [56]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
    """

    Then the response code should be "204"
    When I send a GET request to '/api/latest/configuration/notifications/1'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
      {
          "id": 1,
          "name": "notification-name-updated",
          "timeperiod": {
              "id": 2,
              "name": "none"
          },
          "is_activated": true,
          "messages": [
              {
                  "channel": "Slack",
                  "subject": "Hello world !",
                  "message": "just a small message",
                  "formatted_message": "a formatted message"
              }
          ],
          "users": [
              {
                  "id": 20,
                  "name": "test-user"
              },
              {
                  "id": 21,
                  "name": "user-name1"
              }
          ],
          "contactgroups": [],
          "resources": [
              {
                  "type": "hostgroup",
                  "events": 5,
                  "ids": [
                      {
                          "id": 56,
                          "name": "Printers"
                      }
                  ]
              },
              {
                  "type": "servicegroup",
                  "events": 5,
                  "ids": []
              }
          ]
      }
    """

  Scenario: Enable a notification as an admin user
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 2,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [],
      "is_activated": false
    }
    """
    Then the response code should be "201"

    When I send a PATCH request to '/api/latest/configuration/notifications/1' with body:
    """
    {
      "is_activated": true
    }
    """
    Then the response code should be "204"

  Scenario: Enable a notification that doesn't exist as an admin user
    Given I am logged in
    And a feature flag "notification" of bitmask 2
    And the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 2,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [],
      "is_activated": false
    }
    """
    Then the response code should be "201"

    When I send a PATCH request to '/api/latest/configuration/notifications/2' with body:
    """
    {
      "is_activated": true
    }
    """
    Then the response code should be "404"

  Scenario: Disable a notification as a non-admin user with sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    When I send a PATCH request to '/api/latest/configuration/notifications/1' with body:
    """
    {
      "is_activated": false
    }
    """
    Then the response code should be "204"

  Scenario: Enable a notification as a non-admin user with insufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": false
      }
      """
    Then the response code should be "201"
    And I am logged in with "test-user"/"Centreon@2022"

    When I send a PATCH request to '/api/latest/configuration/notifications/1' with body:
    """
    {
      "is_activated": true
    }
    """
    Then the response code should be "403"

  Scenario: Delete notification definition as an admin user
    Given the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications' with body:
    """
    {
      "name": "notification-name",
      "timeperiod_id": 2,
      "resources": [
        {
          "type": "hostgroup",
          "events": 5,
          "ids": [53,56],
          "extra": {
            "event_services": 2
          }
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
          "message": "just a small message",
          "formatted_message": "a formatted message"
        }
      ],
      "users": [20,21],
      "contactgroups": [],
      "is_activated": true
    }
    """
    Then the response code should be "201"
    When I send a DELETE request to '/api/latest/configuration/notifications/1'
    Then the response code should be "204"

  Scenario: Delete notification definition as a user with sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    When I send a DELETE request to '/api/latest/configuration/notifications/1'
    Then the response code should be "204"

  Scenario: Delete notification definition as a user with insufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56]
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    And I am logged in with "test-user"/"Centreon@2022"
    When I send a DELETE request to '/api/latest/configuration/notifications/1'
    Then the response code should be "403"

  Scenario: Delete notification definition with ID that does not exist
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    When I send a DELETE request to '/api/latest/configuration/notifications/2'
    Then the response code should be "404"

  Scenario: Delete multiple notification definitions as admin user
    Given the following CLAPI import data:
    """
    SG;ADD;service-grp1;service-grp1-alias
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
      Then the response code should be "201"

      When I send a POST request to '/api/latest/configuration/notifications/_delete' with body:
      """
      {
        "ids": [1, 2]
      }
      """
      Then the response code should be "207"
      And the JSON should be equal to:
      """
        {
          "results": [
            {
              "href": "/centreon/api/latest/configuration/notifications/1",
              "status": 204,
              "message": null
            },
            {
              "href": "/centreon/api/latest/configuration/notifications/2",
              "status": 404,
              "message": "Notification not found"
            }
          ]
        }
      """

  Scenario: Delete multiple notification definitions as a non-admin user with sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
    ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;test-user
    SG;ADD;service-grp1;service-grp1-alias
    ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
    SG;ADD;service-grp2;service-grp2-alias
    CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
    CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2
    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53,56],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20,21],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"
    And I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name-2",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [53],
            "extra": {"event_services": 2}
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
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [20],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    When I send a POST request to '/api/latest/configuration/notifications/_delete' with body:
      """
      {
        "ids": [1, 2]
      }
      """
      Then the response code should be "207"
      And the JSON should be equal to:
      """
        {
          "results": [
            {
              "href": "/centreon/api/latest/configuration/notifications/1",
              "status": 204,
              "message": null
            },
            {
              "href": "/centreon/api/latest/configuration/notifications/2",
              "status": 204,
              "message": null
            }
          ]
        }
      """

  Scenario: Delete multiple notification definitions as a non-admin user without sufficient rights
    Given the following CLAPI import data:
    """
    CONTACT;ADD;test-user;test-user;test-user@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test-user;reach_api;1
    """
    And I am logged in with "test-user"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2

    When I send a POST request to '/api/latest/configuration/notifications/_delete' with body:
    """
    {
      "ids": [1, 2]
    }
    """
    Then the response code should be "403"
    And the JSON should be equal to:
    """
    {
      "code": 403,
      "message": "You are not allowed to delete a notification configuration"
    }
    """

  Scenario: List notifiable resources as centreon-broker user
    Given the following CLAPI import data:
      """
      CONTACT;ADD;centreon-broker;centreon-broker;centreon-broker@localservice.com;Centreon@2022;1;1;en_US;local
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;GRANTRW;ACL Menu test;1;Configuration;Notifications;
      ACLRESOURCE;add;ACL Resource test;my alias
      ACLRESOURCE;grant_hostgroup;ACL Resource test;Linux-Servers
      ACLRESOURCE;grant_hostgroup;ACL Resource test;Printers
      ACLGROUP;add;ACL Group test;my alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;addcontact;ACL Group test;test-user
      SG;ADD;service-grp1;service-grp1-alias
      ACLRESOURCE;grant_servicegroup;ACL Resource test;service-grp1
      SG;ADD;service-grp2;service-grp2-alias
      CONTACT;ADD;user-name1;user-alias1;user1@mail.com;Centreon!2021;0;0;;local
      CONTACT;ADD;user-name2;user-alias2;user2@mail.com;Centreon!2021;0;0;;local
      """
    And I am logged in with "centreon-broker"/"Centreon@2022"
    And a feature flag "notification" of bitmask 2

    When I send a GET request to '/api/latest/configuration/notifications/resources'
    Then the response code should be "400"

    When I add 'X-Notifiable-Resources-UID' header equal to ''
    And I send a GET request to '/api/latest/configuration/notifications/resources'
    Then the response code should be "200"
    And the JSON should be equal to:
      """
      {
        "uid": "2c083ee8d86dec12ec5247685a9bc05a",
        "result": []
      }
      """

    When I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [
              53,
              56
            ],
            "extra": {
              "event_services": 2
            }
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [
              1
            ]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [
          20,
          21
        ],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    And I send a POST request to '/api/latest/configuration/notifications' with body:
      """
      {
        "name": "notification-name-2",
        "timeperiod_id": 2,
        "resources": [
          {
            "type": "hostgroup",
            "events": 5,
            "ids": [
              53
            ],
            "extra": {
              "event_services": 2
            }
          },
          {
            "type": "servicegroup",
            "events": 5,
            "ids": [
              1
            ]
          }
        ],
        "messages": [
          {
            "channel": "Slack",
            "subject": "Hello world !",
            "message": "just a small message",
            "formatted_message": "a formatted message"
          }
        ],
        "users": [
          20
        ],
        "contactgroups": [],
        "is_activated": true
      }
      """
    Then the response code should be "201"

    When I add 'X-Notifiable-Resources-UID' header equal to 'hash'
    And I send a GET request to '/api/latest/configuration/notifications/resources'
    Then the response code should be "200"
    And the JSON should be equal to:
      """
      {
        "uid": "f60c7b9b4eef84773c804bbf4e5dfabc",
        "result": [
          {
            "notification_id": 1,
            "hosts": [
              {
                "id": 14,
                "name": "Centreon-Server",
                "alias": "Monitoring Server",
                "events": 5,
                "services": [
                  {
                    "id": 19,
                    "name": "Disk-/",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 20,
                    "name": "Disk-/home",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 21,
                    "name": "Disk-/opt",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 22,
                    "name": "Disk-/usr",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 23,
                    "name": "Disk-/var",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 24,
                    "name": "Load",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 25,
                    "name": "Memory",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 26,
                    "name": "Ping",
                    "alias": null,
                    "events": 2
                  }
                ]
              }
            ]
          },
          {
            "notification_id": 2,
            "hosts": [
              {
                "id": 14,
                "name": "Centreon-Server",
                "alias": "Monitoring Server",
                "events": 5,
                "services": [
                  {
                    "id": 19,
                    "name": "Disk-/",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 20,
                    "name": "Disk-/home",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 21,
                    "name": "Disk-/opt",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 22,
                    "name": "Disk-/usr",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 23,
                    "name": "Disk-/var",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 24,
                    "name": "Load",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 25,
                    "name": "Memory",
                    "alias": null,
                    "events": 2
                  },
                  {
                    "id": 26,
                    "name": "Ping",
                    "alias": null,
                    "events": 2
                  }
                ]
              }
            ]
          }
        ]
      }
      """

    When I add 'X-Notifiable-Resources-UID' header equal to 'f60c7b9b4eef84773c804bbf4e5dfabc'
    And I send a GET request to '/api/latest/configuration/notifications/resources'
    Then the response code should be "304"
