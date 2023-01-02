# Client/Server ZMQ communication

When using ZMQ protocol, all communications are encrypted using symmetric-key encryption based on public/private keys from both client and server.

In a Centreon context, the **client** is the Gorgone daemon running on the **Centreon Central**, the **servers** are the daemon running on **Pollers**.

## Generate private and public keys

On both client and server, generate RSA private and public keys using *centreon* user.

```bash
$ mkdir -p /var/spool/centreon/.gorgone/
$ chmod 700 /var/spool/centreon/.gorgone
$ openssl genrsa -out /var/spool/centreon/.gorgone/privkey.pem 4092
Generating RSA private key, 4092 bit long modulus
...................................++
...........................................................................................................................................................................++
e is 65537 (0x10001)
$ openssl rsa -in /var/spool/centreon/.gorgone/privkey.pem -out /var/spool/centreon/.gorgone/pubkey.pem -pubout -outform PEM
writing RSA key
$ chmod 644 /var/spool/centreon/.gorgone/pubkey.pem
$ chmod 600 /var/spool/centreon/.gorgone/privkey.pem
```

Copy the server public key onto the client in a specific directory (for example */var/spool/centreon/.gorgone/<target_id>*)

## Get the string-formatted JWK thumbprint

On the client, execute the following command:

```bash
$ perl /usr/local/bin/gorgone_key_thumbprint.pl --key-path='/var/spool/centreon/.gorgone/pubkey.pem'
2019-09-30 11:00:00 - INFO - File '/var/spool/centreon/.gorgone/pubkey.pem' JWK thumbprint: pnI6EWkiTbazjikJXRkLmjml5wvVECYtQduJUjS4QK4
```

## Set the configurations

*Make the IDs match Centreon Pollers ID to benefit from [legacy cmd](../docs/modules/core/legacycmd.md) module's actions.*

#### Server

In the */etc/centreon/confid.d/20-gorgoned.yaml* configuration file, add the following directives under the 
*gorgonecore* 
section:

```yaml
gorgone:
  gorgonecore:
    id: 1
    privkey: /var/spool/centreon/.gorgone/privkey.pem
    pubkey: /var/spool/centreon/.gorgone/pubkey.pem
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
```

#### Client

In the */etc/centreon/config.d/20-gorgoned.yaml* configuration file, add the following directives:

```yaml
gorgone:
  gorgonecore:
    id: 2
    external_com_type: tcp
    external_com_path: "*:5556"
    privkey: /var/spool/centreon/.gorgone/privkey.pem
    pubkey: /var/spool/centreon/.gorgone/pubkey.pem
    authorized_clients:
      - key: pnI6EWkiTbazjikJXRkLmjml5wvVECYtQduJUjS4QK4
```

The *authorized_clients* entry allows to define the client public key thumbprint retrieved earlier.
