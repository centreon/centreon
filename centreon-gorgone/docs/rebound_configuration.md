# Architecture

We are showing how to configure gorgone to manage that architecture:

```text
central server <------- rebound server <------- distant poller
```

In our case, we have the following configuration (need to adatp to your configuration).

* Central server:
  * address: 10.30.2.203
* Rebound server:
  * id: 1024 (it must be unique. it's an arbitrary number)
  * address: 10.30.2.67
  * rsa public key thumbprint: NmnPME43IoWpkQoam6CLnrI5hjmdq6Kq8QMUCCg-F4g
* distant server:
  * id: 6 (configured in centreon interface as **zmq**. you get it in the centreon interface)
  * address: 10.30.2.179
  * rsa public key thumbprint: nJSH9nZN2ugQeksHif7Jtv19RQA58yjxfX-Cpnhx09s

# Distant poller

## Installation

The distant server is already installed and gorgone also.

## Configuration

We configure the file **/etc/centreon-gorgone/config.d/40-gorgoned.yaml**:

```text
name:  distant-server
description: Configuration for distant server
gorgone:
  gorgonecore:
    id: 6
    privkey: "/var/lib/centreon-gorgone/.keys/rsakey.priv.pem"
    pubkey: "/var/lib/centreon-gorgone/.keys/rsakey.pub.pem"

  modules:
    - name: action
      package: gorgone::modules::core::action::hooks
      enable: true

    - name: engine
      package: gorgone::modules::centreon::engine::hooks
      enable: true
      command_file: "/var/lib/centreon-engine/rw/centengine.cmd"

    - name: pull
      package: "gorgone::modules::core::pull::hooks"
      enable: true
      target_type: tcp
      target_path: 10.30.2.67:5556
      ping: 1
```

# Rebound server

## Installation

We have installed an centos 7. We install gorgone daemon:

```console
# yum install http://yum.centreon.com/standard/20.04/el7/stable/noarch/RPMS/centreon-release-20.04-1.el7.centos.noarch.rpm
# yum --enablerepo=centreon-unstable* install centreon-gorgone
```

## Configuration

We configure the file **/etc/centreon-gorgone/config.d/40-gorgoned.yaml**:

```text
name:  rebound-server
description: Configuration for rebound-server
gorgone:
  gorgonecore:
    id: 1024
    privkey: "/var/lib/centreon-gorgone/.keys/rsakey.priv.pem"
    pubkey: "/var/lib/centreon-gorgone/.keys/rsakey.pub.pem"
    external_com_type: tcp
    external_com_path: "*:5556"
    authorized_clients:
        - key: nJSH9nZN2ugQeksHif7Jtv19RQA58yjxfX-Cpnhx09s

  modules:
    - name: proxy
      package: "gorgone::modules::core::proxy::hooks"
      enable: true

    - name: pull
      package: "gorgone::modules::core::pull::hooks"
      enable: true
      target_type: tcp
      target_path: 10.30.2.203:5556
      ping: 1
```

# Central server

## Installation

The central server is already installed and gorgone also.

## Configuration

We configure the file **/etc/centreon-gorgone/config.d/40-gorgoned.yaml**:

```text
...
gorgone:
  gorgonecore:
     ...
     authorized_clients:
        - key: NmnPME43IoWpkQoam6CLnrI5hjmdq6Kq8QMUCCg-F4g
     ...
  modules:
     ... 
     - name: register
       package: "gorgone::modules::core::register::hooks"
       enable: true
       config_file: /etc/centreon-gorgone/nodes-register-override.yml
     ...
```

We create the file **/etc/centreon-gorgone/nodes-register-override.yml**:

```text
nodes:
  - id: 1024
    type: pull
    prevail: 1
    nodes:
      - id: 6
        pathscore: 1
```
