export default {
  "panels": [
    {
      "id": null,
      "layout": {
        "height": 4,
        "min_height": 4,
        "min_width": 6,
        "width": 6,
        "x": 0,
        "y": 3
      },
      "name": "centreon-widget-graph",
      "widget_settings": {
        "data": {
          "resources": [
            {
              "resourceType": "host-group",
              "resources": [
                {
                  "id": 53,
                  "name": "Linux-Servers"
                }
              ]
            }
          ],
          "metrics": [
            {
              "criticalHighThreshold": 400,
              "criticalLowThreshold": 0,
              "id": 1,
              "name": "rta",
              "unit": "ms",
              "warningHighThreshold": 200,
              "warningLowThreshold": 0
            }
          ]
        },
        "options": {
          "timeperiod": {
            "start": null,
            "end": null,
            "timePeriodType": 1
          },
          "threshold": {
            "enabled": true,
            "customCritical": null,
            "criticalType": "default",
            "customWarning": null,
            "warningType": "default"
          },
          "refreshInterval": "default",
          "description": {
            "content": null,
            "enabled": true
          }  
        }
      },
      "widget_type": "/widgets/graph"
    },
    {
      "id": null,
      "layout": {
        "height": 4,
        "min_height": 3,
        "min_width": 3,
        "width": 6,
        "x": 6,
        "y": 3
      },
      "name": "centreon-widget-singlemetric",
      "widget_settings": {
        "data": {
          "resources": [
            {
              "resourceType": "host-group",
              "resources": [
                {
                  "id": 53,
                  "name": "Linux-Servers"
                }
              ]
            }
          ],
          "metrics": [
            {
              "id": 26,
              "metrics": [
                {
                  "criticalHighThreshold": 400,
                  "criticalLowThreshold": 0,
                  "id": 1,
                  "name": "rta",
                  "unit": "ms",
                  "warningHighThreshold": 200,
                  "warningLowThreshold": 0
                }
              ],
              "name": "Centreon-Server_Ping"
            }
          ]
        },
        "options": {
          "threshold": {
            "enabled": true,
            "customCritical": null,
            "criticalType": "default",
            "customWarning": null,
            "warningType": "default",
            "baseColor": null
          },
          "refreshInterval": "default",
          "valueFormat": "human",
          "singleMetricGraphType": "gauge",
          "description": {
            "content": null,
            "enabled": true
          }
        }
      },
      "widget_type": "/widgets/singlemetric"
    },
    {
      "id": null,
      "layout": {
        "height": 3,
        "min_height": 3,
        "min_width": 3,
        "width": 12,
        "x": 0,
        "y": 0
      },
      "name": "centreon-widget-generictext",
      "widget_settings": {
        "data": [],
        "options": {
          "description": {
            "content": "{\"root\":{\"children\":[{\"children\":[{\"detail\":0,\"format\":0,\"mode\":\"normal\",\"style\":\"\",\"text\":\"Hello world\",\"type\":\"text\",\"version\":1}],\"direction\":\"ltr\",\"format\":\"\",\"indent\":0,\"type\":\"heading\",\"version\":1,\"tag\":\"h2\"}],\"direction\":\"ltr\",\"format\":\"\",\"indent\":0,\"type\":\"root\",\"version\":1}}",
            "enabled": true
          }
        }
      },
      "widget_type": "/widgets/generictext"
    }
  ]
}