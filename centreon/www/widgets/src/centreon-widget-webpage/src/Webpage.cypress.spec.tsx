import { QueryClient } from '@tanstack/react-query';
import { createStore } from 'jotai';

import Widget from '.';
import { labelWebPagePreview } from './translatedLabels';

const initialize = ({ url }): void => {
  const store = createStore();

  cy.mount({
    Component: (
      <div style={{ height: '100vh', position: 'relative', width: '100%' }}>
        <Widget
          dashboardId={1}
          globalRefreshInterval={{
            interval: null,
            type: 'manual'
          }}
          hasDescription={false}
          id="dashboard"
          panelOptions={{ url }}
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
});
