export const data = JSON.parse(JSON.stringify({
    "id": 1,
    "is_activated": true,
    "name": "blablabla",
    "resources": [
        {
            "type": "hostgroup",
            "ids": [
                {
                    "id": 1,
                    "name": ""
                }
            ], 
            "events": ["up", "down"],
            "extra": {
                "events_services": ["ok", "warning"]
            }
        },
        {
            "type": "servicegroup",
            "ids": [
                {
                    "id": 1,
                    "name": ""
                }
            ], 
            "events": ["ok", "warning"]
        },
        {
            "type": "businessview",
            "ids": [
                {
                    "id": 1,
                    "name": ""
                }
            ], 
            "events": ["ok", "warning"]
        }
    ],
    "users": [
        {
            "id": 1,
            "name": ""
        }
    ],
    "timeperiod": {
        "id": 1,
        "name": ""
    },
    "messages": [
        {
            "channel": "mail",
            "subject": "blblabla",
            "message": "blblabla"
        }
    ]
}))