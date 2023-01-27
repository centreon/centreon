#!/bin/bash

url=http://10.30.2.65

data='{
    "ticket_id": "199",
    "timestamp": 1498125177,
    "user": "test",
    "subject": "mon sujet",
    "links": [
          { "hostname": "Camera-Ip-Datacenter-01", "service_description": "ping", "service_state": "1" },
          { "hostname": "Camera-Ip-Datacenter-04", "host_state": "2" }
    ]
}
'

response=$(curl --data "username=superadmin&password=centreon" $url/centreon/api/index.php?action=authenticate)

token=$(echo "$response" | cut -d: -f 2 | sed 's/"\(.*\)".*/\1/')

curl --header "centreon-auth-token: $token" --header "Content-Type: application/json" --data "$data" "$url/centreon/api/index.php?object=centreon_openticket_history&action=saveHistory"

echo ""

exit 0
