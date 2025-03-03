const configureKB = (url, account, password): void => {
  // Visit the Configuration of 'Knowledge Base' page
  cy.visit('/centreon/main.php?p=50133&o=knowledgeBase')
  cy.wait('@getTimeZone');
  // Wait until the 'knowledge base url' is visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="kb_wiki_url"]');
  // Type a value in the 'Knowledge base url'
  cy.getIframeBody().find('input[name="kb_wiki_url"]').clear().type(url);
  // Type a value in the 'Knowledge base account'
  cy.getIframeBody().find('input[name="kb_wiki_account"]').clear().type(account);
  // Type a value in the 'knowledge base password'
  cy.getIframeBody().find('input[name="kb_wiki_password"]').clear().type(password);
  // Click on the 'Save' button
  cy.getIframeBody().find('input[value="Save"]').click();
  //cy.wait('@getTimeZone');
  cy.exportConfig();
};

const getMediaWikiContainerPort = () => {
  return cy.exec('docker ps --format "{{.Names}}: {{.Ports}}"').then((result) => {
    const containers = result.stdout.split('\n');
    const mediawikiContainer = containers.find(line => line.includes('mediawiki'));
    if (mediawikiContainer) {
      const portMatch = mediawikiContainer.match(/0.0.0.0:(\d+)->/);
      const mediawikiPort = portMatch ? portMatch[1] : 'Port not found';
      cy.log(`MediaWiki Port: ${mediawikiPort}`);
      expect(mediawikiPort).to.match(/^\d+$/); // Ensure it's a number
      return cy.wrap(mediawikiPort);
    } else {
      throw new Error('No MediaWiki container found');
    }
  });
};

export { configureKB, getMediaWikiContainerPort };
