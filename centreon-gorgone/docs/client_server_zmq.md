# Client/Server ZMQ communication

When using ZMQ protocol, all communications are encrypted using symmetric-key encryption based on public/private keys from both client and server.

In a Centreon context, the **client** is the Gorgone daemon running on the **Centreon Central**, the **servers** are the daemon running on **Pollers**.

## Generate private and public keys

On both client and server, generate RSA private and public keys.

```bash
$ mkdir -p /etc/pki/gorgone/
$ openssl genrsa -out /etc/pki/gorgone/privkey.pem 4092
Generating RSA private key, 4092 bit long modulus
...................................++
...........................................................................................................................................................................++
e is 65537 (0x10001)
$ openssl rsa -in /etc/pki/gorgone/privkey.pem -out /etc/pki/gorgone/pubkey.pem -pubout -outform PEM
writing RSA key
$ chmod 644 /etc/pki/gorgone/*
```

Copy the server public key onto the client in a specific directory (for example */etc/pki/gorgone/<target_id>*)

## Get the string-formatted JWK thumbprint

On the client, execute the following command:

```bash
$ perl /usr/local/bin/gorgone_key_thumbprint.pl --key-path='/etc/pki/gorgone/pubkey.pem'
2019-09-30 11:00:00 - INFO - File '/etc/pki/gorgone/pubkey.pem' JWK thumbprint: pnI6EWkiTbazjikJXRkLmjml5wvVECYtQduJUjS4QK4
```

## Set the configurations

*Make the IDs match Centreon Pollers ID to benefit from [legacy cmd](../docs/modules/core/legacycmd.md) module's actions.*

#### Client

In the *gorgoned.yml* configuration file, add the following directives under the *gorgonecore* section:

```yaml
gorgonecore:
  id: 1
  privkey: /etc/pki/gorgone/privkey.pem
  cipher: "Cipher::AES"
  keysize: 32
  vector: 0123456789012345
```

Add the [register](../docs/modules/core/register.md) module and define the path to the dedicated configuration file.

```yaml
modules:
  - name: register
    package: "gorgone::modules::core::register::hooks"
    enable: true
    config_file: /etc/centreon/gorgone-targets.yml
```

Create the file */etc/centreon/gorgone-targets.yml* and fill it with the following configuration:

```yaml
nodes:
  - id: 2
    type: push_zmq
    address: 10.1.2.3
    port: 5556
    server_pubkey: /etc/pki/gorgone/2/pubkey.pem
    client_pubkey: /etc/pki/gorgone/pubkey.pem
    client_privkey: /etc/pki/gorgone/privkey.pem
    cipher: "Cipher::AES"
    keysize: 32
    vector: 0123456789012345
```

#### Server

In the *gorgoned.yml* configuration file, add the following directives under the *gorgonecore* section:

```yaml
gorgonecore:
  id: 2
  external_com_type: tcp
  external_com_path: "*:5556"
  privkey: /etc/pki/gorgone/privkey.pem
  cipher: "Cipher::AES"
  keysize: 32
  vector: 0123456789012345
  authorized_clients:
    - key: pnI6EWkiTbazjikJXRkLmjml5wvVECYtQduJUjS4QK4
```
