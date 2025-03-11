const addPublicCustomView = (): void => {
  // Visit the page 'Home > Custom Views'
  cy.visit('/centreon/main.php?p=103')
  cy.wait('@getTimeZone');
  // Wait until the "Show/Hide edit mode" icon is visible
  cy.waitForElementInIframe('#main-content', 'a[title="Show/Hide edit mode"]');
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
  cy.getIframeBody().contains("button", "Add view").click();
  // Type a value in the 'Name' field
  cy.getIframeBody().find('input[name="name"]').eq(0).type('public-view');
  // Check the 'Public' checkbox
  cy.getIframeBody().find('input[name="public"]').eq(0).click({force: true});
  // Click on the 'Submit' button
  cy.getIframeBody().find('input[name="submit"]').eq(0).click();
  cy.wait('@getViews');
  cy.exportConfig();
};
  
  const addPublicSharedView = () => {
    cy.getIframeBody().contains("button", "Add view").click();
    // Check the 'Load from existing view' radio button
    cy.getIframeBody().find('input[name="create_load[create_load]"]').eq(1).click({force: true});
    // Click on the 'Views' drop down list
    cy.getIframeBody().find('#select2-viewLoad-container').click();
    // Chose the shared public view
    cy.getIframeBody().contains('public-view').click();
    // Click on the 'Submit' button
    cy.getIframeBody().find('input[name="submit"]').eq(0).click();
    cy.wait('@getViews');
  };
  
  export { addPublicCustomView, addPublicSharedView };
  