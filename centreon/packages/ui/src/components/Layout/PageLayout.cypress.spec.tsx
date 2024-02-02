import { T } from 'ramda';

import { PageHeader } from '..';

import { AreaIndicator } from './AreaIndicator';

import { PageLayout } from '.';

const initialize = (): void => {
  cy.mount({
    Component: (
      <PageLayout>
        <PageLayout.Header>
          <PageHeader>
            <PageHeader.Menu>
              <PageLayout.QuickAccess
                create={cy.stub()}
                elements={[
                  {
                    id: 1,
                    name: 'Entity'
                  }
                ]}
                goBack={cy.stub()}
                isActive={T}
                labels={{
                  create: 'Create',
                  goBack: 'Go back'
                }}
                navigateToElement={cy.stub()}
              />
            </PageHeader.Menu>
            <PageHeader.Main>
              <PageHeader.Title description="Description" title="Title" />
            </PageHeader.Main>
            <PageHeader.Actions>Actions</PageHeader.Actions>
          </PageHeader>
        </PageLayout.Header>
        <PageLayout.Body>
          <PageLayout.Actions>
            <AreaIndicator name="Body actions" />
          </PageLayout.Actions>
          <h1>Content</h1>
        </PageLayout.Body>
      </PageLayout>
    )
  });
};

describe('Page layout', () => {
  beforeEach(initialize);

  it('displays the page layout', () => {
    cy.makeSnapshot();
  });

  it('opens the quick access poppin when the corresponding logo is displayed', () => {
    cy.findByTestId('quickaccess').click();

    cy.contains('Entity').should('be.visible');
    cy.contains('Create').should('be.visible');
    cy.contains('Go back').should('be.visible');

    cy.makeSnapshot();
  });
});
