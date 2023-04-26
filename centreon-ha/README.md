# Centreon-HA

## About this project

Centreon-HA project's purpose is to provide a versioned repository of the files that are necessary to run a high availability Centreon cluster.

## Contents

Centreon-HA repository contains:

* scripts (`bin`)
* libraries (`lib`)
* default configuration files (`etc`)
* service definitions (`systemd`)
* custom cluster resource types (`ocf-scripts`)

## How to install Centreon-HA

Copying the Centreon-ha files manually to the places where they are meant to be would be a loss of your time. You'd rather install the `centreon-ha` RPM which can be found on [our public YUM repo](https://yum.centreon.com/standard/21.10/el7/stable/noarch/RPMS/).

But this won't be sufficient to make your Centreon servers work as a cluster. **It is very important that you carefully read [our documentation about Centreon-HA](https://docs.centreon.com/current/en/installation/installation-of-centreon-ha/overview.html)** before starting to install it.

## Contributing

Before opening an issue or a pull request, please have a look at our [contrinution code of conduct](https://github.com/centreon/centreon/blob/master/CONTRIBUTING.md). Remember that you can reach our [community Slack chatroom ;-)](https://centreon.github.io/register-slack/).
