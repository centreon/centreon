/* eslint-disable import/no-unresolved */
import { Suspense } from 'react';

import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router-dom';
import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-generictext/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-generictext/properties.json';

import { Method, TestQueryProvider } from '@centreon/ui';

import {
  dashboardsEndpoint,
  playlistEndpoint,
  playlistsEndpoint
} from '../../api/endpoints';
import {
  labelCreateAPlaylist,
  labelName,
  labelPlaylistProperties,
  labelPlaylists
} from '../../translatedLabels';
import { labelSave } from '../Dashboard/translatedLabels';

import Playlist from './Playlist';
import { router } from './utils';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from 'www/front_src/src/federatedModules/atoms';

const federatedWidgets = [
  {
    ...widgetGenericTextConfiguration,
    moduleFederationName: 'centreon-widget-generictext/src'
  }
];

const initialize = (): {
  useNavigate: unknown;
} => {
  const store = createStore();

  const useNavigate = cy.stub();

  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [widgetGenericTextProperties]);

  cy.stub(router, 'useParams').returns({ dashboardId: 1 });
  cy.stub(router, 'useNavigate').returns(useNavigate);

  cy.fixture('Dashboards/Playlist/playlists.json').then((playlists) => {
    cy.interceptAPIRequest({
      alias: 'getPlaylists',
      method: Method.GET,
      path: `./api/latest${playlistsEndpoint}`,
      response: playlists
    });
  });

  cy.fixture('Dashboards/Playlist/playlist.json').then((playlist) => {
    cy.interceptAPIRequest({
      alias: 'getPlaylist',
      method: Method.GET,
      path: `./api/latest${playlistEndpoint(1)}`,
      response: playlist
    });
  });

  cy.fixture('Dashboards/Playlist/playlist.json').then((playlist) => {
    cy.interceptAPIRequest({
      alias: 'postPlaylist',
      method: Method.POST,
      path: `./api/latest${playlistsEndpoint}`,
      response: playlist
    });
  });

  cy.fixture('Dashboards/Playlist/dashboard.json').then((dashboard) => {
    cy.interceptAPIRequest({
      alias: 'getDashboard1',
      method: Method.GET,
      path: `${dashboardsEndpoint}/1`,
      response: dashboard
    });
  });

  cy.fixture('Dashboards/Playlist/dashboard.json').then((dashboard) => {
    cy.interceptAPIRequest({
      alias: 'getDashboard2',
      method: Method.GET,
      path: `${dashboardsEndpoint}/2`,
      response: dashboard
    });
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestQueryProvider>
          <BrowserRouter>
            <Suspense fallback={<p>Loading...</p>}>
              <Playlist />
            </Suspense>
          </BrowserRouter>
        </TestQueryProvider>
      </Provider>
    )
  });

  return {
    useNavigate
  };
};

describe('Playlist', () => {
  it('displays the first dashboard', () => {
    initialize();

    cy.waitForRequest('@getPlaylist');
    cy.waitForRequest('@getDashboard1');

    cy.contains('Playlist').should('be.visible');
    cy.contains('A small description').should('be.visible');
    cy.contains('Generic text').should('be.visible');

    cy.get('#footer').should('not.be.visible');

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.makeSnapshot();
  });

  it('displays the second dashboard when waiting few seconds', () => {
    initialize();

    cy.waitForRequest('@getPlaylist');
    cy.waitForRequest('@getDashboard1');

    cy.contains('Playlist').should('be.visible');
    cy.contains('A small description').should('be.visible');
    cy.contains('Generic text').should('be.visible');

    cy.get('#footer').should('not.be.visible');

    cy.get('#page-body').trigger('mousemove', 100, 100);

    cy.get('[data-dashboardId="1"]').should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.get('#page-body').trigger('mousemove', 110, 110);

    cy.get('[data-dashboardId="2"]', { timeout: 6_000 }).should(
      'have.attr',
      'data-selected',
      'true'
    );

    cy.get('#page-body').trigger('mousemove', 120, 120);

    cy.waitForRequest('@getDashboard2');

    cy.makeSnapshot();
  });
});

describe('Playlist - Quick access', () => {
  it('opens the playlist config when the quick acces poppin is open and the button is clicked', () => {
    initialize();

    cy.findByTestId('quickaccess').click();
    cy.contains(labelCreateAPlaylist).click();

    cy.contains(labelPlaylistProperties).should('be.visible');

    cy.makeSnapshot();
  });

  it('navigates to the created playlist when the quick acces poppin is open, the button is clicked and the form is fullfilled', () => {
    const { useNavigate } = initialize();

    cy.findByTestId('quickaccess').click();
    cy.contains(labelCreateAPlaylist).click();

    cy.findByLabelText(labelName).type('playlist');

    cy.contains(labelSave).click();

    cy.waitForRequest('@postPlaylist').then(() => {
      expect(useNavigate).to.have.been.calledWith(
        '/home/dashboards/playlists/1'
      );
    });

    cy.makeSnapshot();
  });

  it('navigates to the plyalists listing when the quick access popping is open and the button is clicked', () => {
    const { useNavigate } = initialize();

    cy.findByTestId('quickaccess').click();
    cy.contains(labelPlaylists)
      .click()
      .then(() => {
        expect(useNavigate).to.have.been.calledWith(
          '/home/dashboards/playlists'
        );
      });
  });

  it('changes the playlist when the quick access popping is open and the button is clicked', () => {
    const { useNavigate } = initialize();

    cy.findByTestId('quickaccess').click();
    cy.contains('Playlist 1').should('have.attr', 'data-is-active', 'true');
    cy.contains('Playlist 2')
      .click()
      .then(() => {
        expect(useNavigate).to.have.been.calledWith(
          '/home/dashboards/playlists/2'
        );
      });
  });
});
