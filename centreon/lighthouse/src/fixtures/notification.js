export default {
  "contactgroups": [],
  "is_activated": true,
  "messages": [
    {
      "channel": "Email",
      "formatted_message": "<p dir=\"ltr\"><b><strong class=\"css-1jxftah-bold\">Centreon notification</strong></b><br><br><span>Notification Type: </span><b><strong class=\"css-1jxftah-bold\">{{NOTIFICATIONTYPE}}</strong></b><br><br><span>Resource: {{NAME}}</span><br><span>ID: {{ID}}</span><br><br><span>State: </span><b><strong class=\"css-1jxftah-bold\">{{STATE}}</strong></b><br><br><span>Date/Time: {{SHORTDATETIME}}</span><br><br><span>Additional Info: {{OUTPUT}}</span></p>",
      "message": "{\"root\":{\"children\":[{\"children\":[{\"detail\":0,\"format\":1,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Centreon notification\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Notification Type: \",\"type\":\"text\",\"version\":1},{\"detail\":0,\"format\":1,\"mode\":\"normal\",\"style\":\"\",\"text\":\"{{NOTIFICATIONTYPE}}\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Resource: {{NAME}}\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"ID: {{ID}}\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"State: \",\"type\":\"text\",\"version\":1},{\"detail\":0,\"format\":1,\"mode\":\"normal\",\"style\":\"\",\"text\":\"{{STATE}}\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Date/Time: {{SHORTDATETIME}}\",\"type\":\"text\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"type\":\"linebreak\",\"version\":1},{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Additional Info: {{OUTPUT}}\",\"type\":\"text\",\"version\":1}],\"direction\":\"ltr\",\"format\":\"\",\"indent\":0,\"type\":\"paragraph\",\"version\":1}],\"direction\":\"ltr\",\"format\":\"\",\"indent\":0,\"type\":\"root\",\"version\":1}}",
      "subject": "{{NOTIFICATIONTYPE}} alert - {{NAME}} is {{STATE}}"
    }
  ],
  "name": "Lighthouse",
  "resources": [
    {
      "events": 2,
      "extra": {
        "event_services": 0
      },
      "ids": [
        53
      ],
      "type": "hostgroup"
    },
    {
      "events": 0,
      "ids": [],
      "type": "servicegroup"
    }
  ],
  "timeperiod_id": 1,
  "users": [
    1
  ]
}