import { QueryClient } from '@tanstack/react-query';
import { createStore } from 'jotai';

import Widget from '.';
import { labelWebPagePreview } from './translatedLabels';

const initialize = ({   widgetId = "1" , url,globalRefreshInterval = {
  interval: null,
  type: 'manual',

} }): void => {
  const store = createStore();

  cy.clock(); 
  
  cy.mount({
    Component: (
      <div style={{ height: '100vh', position: 'relative', width: '100%' }}>
        <Widget
          dashboardId={1}
          globalRefreshInterval={globalRefreshInterval}
          hasDescription={false}
          id={widgetId}
          panelOptions={{ url 
           }}
          queryClient={new QueryClient()}
          refreshCount={0}
          store={store}
          widgetPrefixQuery="prefix"
        />
      </div>
    )
  });
};

describe('Web page', () => {
  it('displays the widget preview if the URL is empty', () => {
    initialize({ url: '' });

    cy.findByTestId('Webpage Display').should('not.exist');

    cy.findByText(labelWebPagePreview).should('be.visible');
  });

  it('displays the page content if the URL is valid', () => {
    initialize({ url: 'https://docs.centreon.com' });

    cy.findByTestId('Webpage Display').should('be.visible');
  });

  it('displays the page content if the URL is valid and does not contain the http prefix', () => {
    initialize({ url: 'docs.centreon.com' });

    cy.findByTestId('Webpage Display').should('be.visible');
  });

  it('refreshes the iframe content at the specified interval', () => {
    initialize({
      url: 'docs.centreon.com',
      globalRefreshInterval: { interval: 1000, type: 'automatic' }
    });

    cy.findByTestId('Webpage Display').as('iframe');

    // Wait for the interval to pass and check if the iframe's src is refreshed
    cy.tick(1000);
    cy.get('@iframe').should('have.attr', 'src', 'http://docs.centreon.com');

    cy.tick(1000);
    cy.get('@iframe').should('have.attr', 'src', 'http://docs.centreon.com');
  });

  it('generates the correct iframe ID based on widgetId', () => {
    const widgetId = '1';
    initialize({
      url: 'docs.centreon.com',
      globalRefreshInterval: { interval: null, type: 'manual' },
      widgetId
    });

    cy.get(`#Webpage_${widgetId}`).should('be.visible');
  });

  it('transforms a URL correctly when missing http prefix', () => {
    initialize({ url: 'docs.centreon.com', globalRefreshInterval: { interval: null, type: 'manual' } });

    cy.findByTestId('Webpage Display').should('have.attr', 'src', 'http://docs.centreon.com');
  });
});
