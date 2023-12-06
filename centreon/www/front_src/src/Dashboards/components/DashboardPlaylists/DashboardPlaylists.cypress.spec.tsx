import { BrowserRouter } from 'react-router-dom';

import { TestQueryProvider } from '@centreon/ui';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';

import DashboardPlaylistsOverview from './DashboardPlaylistsOverview';

const initialize = (): void => {
  cy.mount({
    Component: (
      <TestQueryProvider>
        <BrowserRouter>
          <DashboardPlaylistsOverview />
        </BrowserRouter>
      </TestQueryProvider>
    )
  });
};

describe('Playlists', () => {
  it('displays the landing screen when there is no data', () => {
    initialize();

    cy.contains(labelWelcomeToThePlaylistInterface).should('be.visible');
    cy.contains(labelCreateAPlaylist).should('be.visible');

    cy.makeSnapshot();
  });
});
