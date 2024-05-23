import PublicPagesManager from './PublicPagesManager';
import { usePageResolver } from './usePageResolver';

const initialize = (pathname: string): void => {
  cy.stub(usePageResolver, 'useLocation').returns({ pathname });

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <PublicPagesManager />
      </div>
    )
  });
};

describe('Public pages manager', () => {
  it('displays a not found page when route does not exist', () => {
    initialize('/public/page/not/found');

    cy.contains('404').should('be.visible');
    cy.contains('This page could not be found').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays an error when the page fails to load', () => {
    initialize('/public/dashboards/playlists/hash');

    cy.contains('404').should('not.exist');
    cy.contains('Cannot load module').should('be.visible');

    cy.makeSnapshot();
  });
});
