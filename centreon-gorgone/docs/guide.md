# Gorgone protocol

"gorgone-core" (main mandatory module) can have 2 interfaces:

* Internal: uncrypted dialog (used by internal modules. Commonly in ipc)
* External: crypted dialog (used by third-party clients. Commonly in tcp)

## Handshake scenario

Third-party clients have to use the ZeroMQ library and the following process:

1. Client: need to create an uniq identity (will be used in "zmq_setsockopt" and "ZMQ_IDENTITY")
2. Client -> Server: ask the server pubkey

    ```text
    [GETPUBKEY]
    ```

3. Server -> Client: send back the pubkey

    ```text
    [PUBKEY] [base64 encoding pubkey]
    ```

4. Client -> Server: send the following message with HELO crypted with the public key of the server (and base64 encoding) and provides client pubkey (base64 encoding):

    ```text
    [HOSTNAME] [CLIENTPUBKEY] [HELO]
    ```

5. Server -> Client: uncrypt the client message:

    * If uncrypted message result is not "HELO", server refuses the connection and send it back:

    ```text
    [ACK] [] { "code": 1, "data": { "message": "handshake issue" } }
    ```

    * If uncrypted message result is "HELO", server accepts the connection if the clientpubkey is authorized. It creates a symmetric key and send the following message crypted with client pubkey:

    ```text
    [KEY] { "hostname": "xxxx", "key": "ab0182xxxx", "iv": "ab0182xxx", "cipher": "AES", "padding": 1 }
    ```

4. Client: uncrypts the server message with its private key.
5. Client and Server uses the symmetric key+base64 encoding to dialog.

The server keeps sessions for 24 hours since the last message of the client.

Otherwise, it purges the identity/symmetric-key of the client.

If a third-party client with the same identity try to open a new session, the server deletes the old identity/symmetric-key.

Be sure to have the same parameters to crypt/uncrypt with the symmetric key. Commonly: 'AES' cipher, keysize of 32 bytes, vector '0123456789012345'.

## Client request

After a successful handshake, client requests use the following syntax:

```text
[ACTION] [TOKEN] [TARGET] DATA
```

* ACTION: the request, for example 'COMMAND' or 'ENGINECOMMAND'. It depends of the target server capabilites,
* TOKEN: can be used to create some "sessions". If empty, the server creates an uniq token for each requests,
* TARGET: which "gorgoned" must execute the request. With the following option, you can execute a command on a specific server through another. The poller ID is needed. If empty, the server (which is connected with the client) is the target.
* DATA: JSON stream. It depends on the request.

For each client requests, the server get an immediate response:

```text
[ACK] [TOKEN] { "code": "x", "data": { "message": "xxxxx" } }
```

* TOKEN: a uniq ID to follow the request,
* DATA: a JSON stream

  * 0 : OK
  * 1 : NOK

There are some exceptions for 'CONSTATUS' and 'GETLOG' requests.

## Core requests

### CONSTATUS

The following request gives you a table with the last ping response of "gorgoned" nodes connected to the server.
The command is useful to know if some pollers are disconnected.

The client request:

```text
[CONSTATUS] [] []
```

The server response:

```text
[CONSTATUS] [token_id] DATA
```

An example of the JSON stream:

```json
{
    "code": 1,
    "data": {
      "action": "constatus",
      "mesage": "ok",
      "data": {
        "last_ping_sent": "xxxx",
        "last_ping_recv": "xxxx",
        "nodes": {
          "1": "xxx",
          "2": "xxx"
        }
      }
    }
}
```

'last_ping' and 'entries' values are unix timestamp in seconds.

The 'last_ping' value is the date when the daemon have launched a PING broadcast to the poller connected.

The 'entries' values are the last time the poller have responded to the PING broadcast.

### GETLOG

The following request gives you the capability to follow your requests. "gorgone" protocol is asynchronous.

An example: when you request a command execution, the server gives you a direct response and a token. This token can be used to know what happened to your command.

The client request:

```text
[GETLOG] [TOKEN] [TARGET] { "code": "xx", "ctime": "xx", "etime": "xx", "token": "xx", "id": "xx" }
```

At least one of the 5 values must be defined:

* code: get logs if code = value
* token: get logs if token = value
* ctime: get logs if creation time in seconds >= value
* etime: get logs if event time in seconds >= value
* id: get logs if id > value

The 'etime' value gives the time when the event has occured.

The 'ctime' value gives the time when the server has stored the log in its database.

The server response:

```text
[ACK] [token_id] DATA
```

An example of the json stream:

```json
{
  "code": 1,
  "data": {
    "action": "getlog",
    "message": "ok",
    "result": [
      {
        "id": 10,
        "token": "xxxx",
        "code": 1,
        "etime": 1419252684,
        "ctime": 1419252686,
        "data": "xxxx",
      },
      {
        "id": 100,
        "token": "xxxx",
        "code": 1,
        "etime": 1419252688,
        "ctime": 1419252690,
        "data": "xxxx",
      }
    ]
  }
}
```

Each 'gorgoned' nodes store its logs. But every minute (by default), the Central server gets the new logs of its connected nodes and stores it.

A client can force a synchronization with the following request:

```text
[GETLOG] [] [target_id]
```

The client have to set the target ID (it can be the Poller ID).

### PUTLOG

The request shouldn't be used by third-party program. It's commonly used by the internal modules.

The client request:

```text
[PUTLOG] [TOKEN] [TARGET] { "code": xxx, "etime": "xxx", "token": "xxxx", "data": { some_datas } }
```

### REGISTERNODES

The request shouldn't be used by third-party program. It's commonly used by the internal modules.

The client request (no carriage returns. only for reading):

```text
[REGISTERNODES] [TOKEN] [TARGET] { "nodes": [
    { "id": 20, "type": "pull" },
    { "id": 100, "type": "push_ssh", "address": "10.0.0.1", "ssh_port": 22 },
    {
      "id": 150, "type": "push_zmq", "address": "10.3.2.1",
      "nodes": [ { "id": 400, { "id": 455 } ]
    }
  ]
}
```

## Common codes

Common code responses for all module requests:

* 0: action proceed
* 1: action finished OK
* 2: action finished KO

Modules can have extra codes.

# FAQ

## Which modules should I enable ?

A Central with gorgoned should have the following modules:

* action,
* proxy,
* cron,
* httpserver.

A Poller with gorgoned should have the following modules:

* action,
* pull (if the connection to the Central should be opened by the Poller).

## I want to create a client. How should I proceed ?

First, you must choose a language which can use ZeroMQ library and have some knowledge about ZeroMQ.

I recommend the following scenario:

* Create a ZMQ_DEALER,
* Manage the handshake with the server (see :ref:`handshake-scenario`),
* Do a request:
  * If you don't need to get the result: close the connection,
  * If you need to get the result:  
    1. Get the token,
    2. If you have used a target, force a synchronization with 'GETLOG' (without token),
    3. Do a 'GETLOG' request with the token to get the result,
    4. Repeat actions 2 and 3 if you don't have a result yet (you should stop after X retries).

You can inspire from the code of '[test-client.pl](../contrib/test-client.pl)'.
