import { createStore, Provider } from 'jotai';
import { inc } from 'ramda';

import { displayedDashboardAtom } from '../../atoms';

import Footer from './Footer';

const dashboards = Array(20)
  .fill(0)
  .map((_, idx) => ({
    id: inc(idx),
    name: `Dashboard ${idx}`,
    order: inc(idx)
  }));

const initialize = (
  displayedDashboard: number | null = 1
): ReturnType<typeof createStore> => {
  const store = createStore();

  store.set(displayedDashboardAtom, displayedDashboard);

  cy.mount({
    Component: (
      <Provider store={store}>
        <div id="page-body" style={{ height: '100vh' }}>
          <Footer dashboards={dashboards} />
        </div>
      </Provider>
    )
  });

  return store;
};

describe('Footer', () => {
  it('displays the footer when the mouse is moved', () => {
    initialize();
    cy.get('#footer').should('not.be.visible');

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('#footer').should('be.visible');

    cy.get('[data-is-playing="true"]').should('be.visible');
    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.makeSnapshot();
  });

  it('hides the footer when the mouse stops moving', () => {
    initialize();
    cy.get('#footer').should('not.be.visible');

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('#footer').should('be.visible');

    cy.get('#footer', { timeout: 6_000 }).should('not.be.visible');

    cy.makeSnapshot();
  });

  it('stops the rotation when the corresponding button is clicked', () => {
    initialize();

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-is-playing="true"]').click();

    cy.get('[data-is-playing="false"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('goes to the next dashboard when the corresponding button is clicked', () => {
    initialize();

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.findByTestId('next').click();

    cy.get('[data-dashboardId="2"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.makeSnapshot();
  });

  it('goes to the previous dashboard when the corresponding button is clicked', () => {
    initialize();

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.findByTestId('previous').click();

    cy.get('[data-dashboardId="20"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.makeSnapshot();
  });

  it('displays the dashboard as selected when a dashboard is clicked', () => {
    initialize();

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="3"]').click();

    cy.get('[data-dashboardId="3"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.makeSnapshot();
  });

  it('does not display any dashboard as selected when no dashboard is selected', () => {
    initialize(null);

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'false'
    );
    cy.get('[data-dashboardId="2"]').should(
      'have.attr',
      'data-selected',
      'false'
    );
    cy.get('[data-dashboardId="3"]').should(
      'have.attr',
      'data-selected',
      'false'
    );

    cy.makeSnapshot();
  });
});
