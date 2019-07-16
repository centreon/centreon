export default {
    "status": true,
    "result": {
        "pagination": {
            "total": 2,
            "offset": 0,
            "limit": 2
        },
        "entities": [
            {
                "id": 1,
                "name": "Centreon-Server - Load",
                "type": "Service",
                "activate": 1,
                "impact": {
                    "type": "word",
                    "critical": "3",
                    "warning": "3",
                    "unknown": "4"
                }
            },
            {
                "id": 2,
                "name": "Centreon-Server - Ping",
                "type": "Service",
                "activate": 1,
                "impact": {
                    "type": "value",
                    "critical": "100",
                    "warning": "50",
                    "unknown": "75"
                }
            }
        ]
    }
}