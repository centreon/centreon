const visitCustomViewPage = () => {
    cy.visit('/centreon/main.php?p=103');
    cy.wait('@getTimeZone');
}

const addCustomView = (name, isPublic): void => {
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
    cy.getIframeBody().contains("button", "Add view").click({force: true});
    cy.wait('@action');
    // Type a value in the 'Name' field
    cy.getIframeBody().find('input[name="name"]').eq(0).type(name);
    if(isPublic){
        // Check the 'Public' checkbox
        cy.getIframeBody().find('input[name="public"]').eq(0).click({force: true});
    }
    // Click on the 'Submit' button
    cy.getIframeBody().find('input[name="submit"]').eq(0).click();
    cy.wait('@getViews');
};
  
const addSharedView = (name) => {
    cy.getIframeBody().contains("button", "Add view").click({force: true});
    // Check the 'Load from existing view' radio button
    cy.getIframeBody().find('input[name="create_load[create_load]"]').eq(1).click({force: true});
    // Click on the 'Views' drop down list
    cy.getIframeBody().find('#select2-viewLoad-container').click();
    // Chose the shared public view
    cy.getIframeBody().contains(name).click();
    // Click on the 'Submit' button
    cy.getIframeBody().find('input[name="submit"]').eq(0).click();
    cy.wait('@getViews');
};

const shareCustomView = (userOrGroupTag, name) => {
    cy.getIframeBody().contains('button', 'Share view').click();
    // Click on the 'Unlocked user or groups' input
    cy.getIframeBody().find(`input[placeholder="${userOrGroupTag}"]`).click();
    // Chose the user/ group name
    cy.getIframeBody().find(`div[title="${name}"]`).click();
    // Click on the 'Share' button
    cy.getIframeBody().find('input[name="submit"][value="Share"]').click();
    cy.exportConfig();
};

const deleteCustomView = () => {
    cy.waitForElementInIframe('#main-content', 'button.deleteView');
    // Click on the 'Delete view' button
    cy.getIframeBody().find('button.deleteView').click({force: true});
    // Click on the delete in the confirmation popup
    cy.getIframeBody().find('#deleteViewConfirm .bt_danger').click();
};
  
export { visitCustomViewPage, addCustomView, addSharedView, shareCustomView, deleteCustomView };
  