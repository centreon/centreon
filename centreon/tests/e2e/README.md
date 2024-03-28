# Tests End-to-End with Cypress.io

## Best Practices

Cypress : https://docs.cypress.io/guides/references/best-practices.html

## Require

- docker + docker-compose
- npm >= 5.2 (to use npx included)
- pnpm >= 8

### General infos

All the tests should run fine, except some of them like "SAML, openid.." they need a small change in the config file to be able to run for windows/mac users.

## Step by Step

### Installing the dependencies

This will install all the dependencies that are required in the entire project to be able to run the tests.

Assuming you are in the base root of the project "centreon".

> cd centreon/tests/e2e

> pnpm install --frozen-lockfile

### Running the tests

After the dependencies has been installed, to be able to run cypress in GUI mode:

> pnpm cypress:open

Then you'll be able to view all the E2E specs that you can run.

NB: All the tests should run fine except for some of them that require small changes in the config file (Ex: SAML/OPENID provider...).

For linux it should be fine but for windows/mac users need to make small changes in "common.ts" file of the test case.

EX: For Openid in the "common.ts" file that is located in "OpenID-connect" folder change the ip "172.17.0.3" by "localhost:8080".