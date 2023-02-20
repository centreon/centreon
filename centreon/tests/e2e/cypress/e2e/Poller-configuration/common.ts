const insertPollerConfigAclUser = (): Cypress.Chainable => {
  return cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/poller-configuration-acl-user.json.json'
  );
};

const getPoller = (pollerName: string): Cypress.Chainable => {
  const query = `SELECT id FROM nagio_server WHERE name = ${pollerName}`;
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon <<< "${query}"`;

  return cy
    .exec(command, { failOnNonZeroExit: true, log: true })
    .then(({ code, stdout, stderr }) => {
      if (!stderr && code === 0) {
        const pollerId = parseInt(stdout.split('\n')[1], 10);

        return cy.wrap(pollerId || '0');
      }

      return cy.log(`Can't execute command on database.`);
    });
};

export { insertPollerConfigAclUser, getPoller };
