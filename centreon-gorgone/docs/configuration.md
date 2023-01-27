# Configuration

| Directive     | Description                                                             |
| :------------ | :---------------------------------------------------------------------- |
| name          | Name of the configuration                                               |
| description   | Short string to decribe the configuration                               |
| configuration | First configuration entry point                                         |
| centreon      | Entry point to set Centreon configuration                               |
| database      | Entry point to set Centreon databases data source names and credentials |
| gorgonecore   | Entry point to set Gorgone main configuration                           |
| modules       | Table to load and configuration Gorgone modules                         |

## *database*

Usefull in a Centreon Central installation to access Centreon databases.

| Directive | Description                      |
| :-------- | :------------------------------- |
| dsn       | Data source name of the database |
| username  | Username to access the database  |
| password  | Username's password              |

#### Example

```yaml
configuration:
  centreon:
    database:
      db_configuration:
        dsn: "mysql:host=localhost;dbname=centreon"
        username: centreon
        password: centreon
      db_realtime:
        dsn: "mysql:host=localhost;dbname=centreon_storage"
        username: centreon
        password: centreon
```

## *gorgonecore*

| Directive             | Description                                                             | Default value                                  |
| :-------------------- | :---------------------------------------------------------------------- | :--------------------------------------------- |
| internal_com_type     | Type of the internal ZMQ socket                                         | `ipc`                                          |
| internal_com_path     | Path to the internal ZMQ socket                                         | `/tmp/gorgone/routing.ipc`                     |
| internal_com_crypt    | Crypt internal communication                                            | `true`                                         |
| internal_com_cipher   | Internal communication cipher                                           | `AES`                                          |
| internal_com_padding  | Internal communication padding                                          | `1` (mean: PKCS5)                              |
| internal_com_keysize  | Internal communication key size                                         | `32` (bytes)                                   |
| internal_com_rotation | Internal communication time before key rotation                         | `1440` (minutes)                               |
| external_com_type     | Type of the external ZMQ socket                                         | `tcp`                                          |
| external_com_path     | Path to the external ZMQ socket                                         | `*:5555`                                       |
| external_com_cipher   | Cipher used for encryption                                              | `AES`                                          |
| external_com_keysize  | Size in bytes of the symmetric encryption key                           | `32`                                           |
| external_com_padding  | External communication padding                                          | `1` (mean: PKCS5)                              |
| external_com_rotation | External communication time before key rotation                         | `1440` (minutes)                               |
| timeout               | Time in seconds before killing child processes when stopping Gorgone    | `50`                                           |
| gorgone_db_type       | Type of the Gorgone database                                            | `SQLite`                                       |
| gorgone_db_name       | Path and name of the database                                           | `dbname=/var/lib/centreon-gorgone/history.sdb` |
| gorgone_db_host       | Hostname/IP address of the server hosting the database                  |                                                |
| gorgone_db_port       | Port of the database listener                                           |                                                |
| gorgone_db_user       | Username to access the database                                         |                                                |
| gorgone_db_password   | Username's password                                                     |                                                |
| hostname              | Hostname of the server running Gorgone                                  | Result of *hostname* system function.          |
| id                    | Identifier of server running Gorgone                                    | None. Must be unique over all Gorgone daemons. |
| privkey               | Path to the Gorgone core private key                                    | `keys/rsakey.priv.pem`                         |
| pubkey                | Path to the Gorgone core public key                                     | `keys/rsakey.pub.pem`                          |
| fingerprint_mode      | Validation mode of zmq nodes to connect (can be: always, first, strict) | `first`                                        |
| fingerprint_mgr       | Hash of the definition class to store fingerprints                      |                                                |
| authorized_clients    | Table of string-formated JWK thumbprints of clients public key          |                                                |
| proxy_name            | Name of the proxy module definition                                     | `proxy` (loaded internally)                    |

#### Example

```yaml
configuration:
  gorgone:
    gorgonecore:
      internal_com_type: ipc
      internal_com_path: /tmp/gorgone/routing.ipc
      external_com_type: tcp
      external_com_path: "*:5555"
      timeout: 50
      gorgone_db_type: SQLite
      gorgone_db_name: dbname=/var/lib/centreon-gorgone/history.sdb
      gorgone_db_host:
      gorgone_db_port:
      gorgone_db_user:
      gorgone_db_password:
      hostname:
      id:
      privkey: keys/central/privkey.pem
      cipher: "Cipher::AES"
      keysize: 32
      vector: 0123456789012345
      fingerprint_mode: first
      fingerprint_mgr:
        package: gorgone::class::fingerprint::backend::sql
      authorized_clients:
        - key: pnI6EWkiTbazjikJXRkLmjml5wvVECYtQduJUjS4QK4
      proxy_name: proxy
```

## *modules*

See the *configuration* titles of the modules documentations listed [here](../docs/modules.md).
