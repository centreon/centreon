# API

Centreon Gorgone provides a RestAPI through its HTTP server module.

## Internal endpoints

### Get Nodes Connection Status

| Endpoint | Method |
| :- | :- |
| /api/internal/constatus | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Example

```bash
curl --request GET "https://hostname:8443/api/internal/constatus" \
  --header "Accept: application/json"
```

### Get Public Key Thumbprint

| Endpoint | Method |
| :- | :- |
| /api/internal/thumbprint | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Example

```bash
curl --request GET "https://hostname:8443/api/internal/thumbprint" \
  --header "Accept: application/json"
```

### Get Runtime Informations And Statistics

| Endpoint | Method |
| :- | :- |
| /api/internal/information | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Example

```bash
curl --request GET "https://hostname:8443/api/internal/information" \
  --header "Accept: application/json"
```

#### Response example

```json
{
    "action": "information",
    "data": {
        "modules": {
            "httpserver": "gorgone::modules::core::httpserver::hooks",
            "dbcleaner": "gorgone::modules::core::dbcleaner::hooks",
            "cron": "gorgone::modules::core::cron::hooks",
            "engine": "gorgone::modules::centreon::engine::hooks",
            "action": "gorgone::modules::core::action::hooks",
            "broker": "gorgone::modules::centreon::broker::hooks",
            "nodes": "gorgone::modules::centreon::nodes::hooks",
            "legacycmd": "gorgone::modules::centreon::legacycmd::hooks"
        },
        "api_endpoints": {
            "GET_/centreon/broker/statistics": "BROKERSTATS",
            "GET_/internal/thumbprint": "GETTHUMBPRINT",
            "GET_/core/cron/definitions": "GETCRON",
            "GET_/internal/information": "INFORMATION",
            "POST_/core/cron/definitions": "ADDCRON",
            "POST_/core/action/command": "COMMAND",
            "POST_/centreon/engine/command": "ENGINECOMMAND",
            "POST_/core/proxy/remotecopy": "REMOTECOPY",
            "PATCH_/core/cron/definitions": "UPDATECRON",
            "DELETE_/core/cron/definitions": "DELETECRON",
            "GET_/internal/constatus": "CONSTATUS"
        },
        "counters": {
            "external": {
                "total": 0
            },
            "total": 183,
            "internal": {
                "legacycmdready": 1,
                "brokerready": 1,
                "addcron": 1,
                "cronready": 1,
                "centreonnodesready": 1,
                "httpserverready": 1,
                "command": 51,
                "putlog": 75,
                "dbcleanerready": 1,
                "information": 1,
                "brokerstats": 8,
                "total": 183,
                "setcoreid": 2,
                "getlog": 37,
                "engineready": 1,
                "actionready": 1
            },
            "proxy": {
                "total": 0
            }
        }
    },
    "message": "ok"
}
```

## Modules endpoints

The available endpoints depend on which modules are loaded.

Endpoints are basically built from:

* API root,
* Module's namespace,
* Module's name,
* Action

#### Example

```bash
curl --request POST "https://hostname:8443/api/core/action/command" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "[
    {
        \"command\": \"echo 'Test command'\"
    }
]"
```

Find more informations directly from modules documentations [here](../docs/modules.md).

As Centreon Gorgone is asynchronous, those endpoints will return a token corresponding to the action.

#### Example

```json
{
    "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1"
}
```

## Log endpoint

To retrieve the logs, a specific endpoint can be called as follow.

| Endpoint | Method |
| :- | :- |
| /api/log/:token | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Path variables

| Variable | Description |
| :- | :- |
| token | Token of the action |

#### Example

```bash
curl --request GET "https://hostname:8443/api/log/3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1" \
  --header "Accept: application/json"
```

#### Response example

```json
{
    "data": [
        {
            "ctime": 1576083003,
            "etime": 1576083003,
            "id": "15638",
            "instant": 0,
            "data": "{\"message\":\"commands processing has started\",\"request_content\":[{\"timeout\":10,\"command\":\"echo 'Test command'\"}]}",
            "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1",
            "code": 0
        },
        {
            "ctime": 1576083003,
            "etime": 1576083003,
            "id": "15639",
            "instant": 0,
            "data": "{\"metadata\":null,\"message\":\"command has started\",\"command\":\"echo 'Test command'\"}",
            "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1",
            "code": 0
        },
        {
            "ctime": 1576083003,
            "etime": 1576083003,
            "id": "15640",
            "instant": 0,
            "data": "{\"metadata\":null,\"metrics\":{\"duration\":0,\"start\":1576083003,\"end\":1576083003},\"message\":\"command has finished\",\"command\":\"echo 'Test command'\",\"result\":{\"exit_code\":0,\"stdout\":\"Test command\"}}",
            "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1",
            "code": 2
        },
        {
            "ctime": 1576083003,
            "etime": 1576083003,
            "id": "15641",
            "instant": 0,
            "data": "{\"message\":\"commands processing has finished\"}",
            "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1",
            "code": 2
        }
    ],
    "token": "3f25bc3a797fe989d1fb052b1886a806e73fe2d8ccfc6377ee3d4490f8ad03c02cb2533edcc1b3d8e1770e28d6f2de83bd98923b66c0c33395e5f835759de4b1",
    "message": "Logs found"
}
```

## Errors

### Unauthorized

```json
{
    "error":"http_error_401",
    "message":"unauthorized"
}
```

### Forbidden

```json
{
    "error":"http_error_403",
    "message":"forbidden"
}
```

### Unknown endpoint

```json
{
    "error":"endpoint_unknown",
    "message":"endpoint not implemented"
}
```

### Unknown method

```json
{
    "error": "method_unknown",
    "message": "Method not implemented"
}
```

### No logs for provided token

```json
{
    "error": "no_log",
    "message": "No log found for token",
    "data": [],
    "token": "<token>"
}
```

### JSON decoding error for request

```json
{
    "error":"decode_error",
    "message":"Cannot decode response"
}
```

### JSON encoding error for response

```json
{
    "error":"encode_error",
    "message":"Cannot encode response"
}
```

### No results for internal actions

```json
{
    "error":"no_result",
    "message":"No result found for action <name of action>"
}
```

### No token found when using wait parameter

```json
{
    "error":"no_token",
    "message":"Cannot retrieve token from ack"
}
```
