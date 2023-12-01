import { Provider, createStore } from 'jotai';

import { Method, QueryProvider } from '@centreon/ui';

import {
  dashboardsEndpoint,
  playlistEndpoint,
  playlistsEndpoint
} from '../../../api/endpoints';
import { playlistConfigInitialValuesAtom } from '../atoms';
import {
  labelAddADashboard,
  labelCancel,
  labelDescription,
  labelPlaylistName,
  labelPlaylistProperties,
  labelRotationTime,
  labelSelectDashboards
} from '../../../translatedLabels';
import { labelRequired, labelSave } from '../../../Dashboard/translatedLabels';

import PlaylistConfig from './PlaylistConfig';
import { initialValue } from './utils';

const initializePlaylistConfigCreation = (): void => {
  const store = createStore();

  cy.fixture(`Dashboards/dashboards.json`).then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.interceptAPIRequest({
    alias: 'postPlaylistConfig',
    method: Method.POST,
    path: `**${playlistsEndpoint}`,
    response: {
      id: 1
    },
    statusCode: 201
  });

  store.set(playlistConfigInitialValuesAtom, initialValue);

  cy.mount({
    Component: (
      <QueryProvider>
        <Provider store={store}>
          <PlaylistConfig />
        </Provider>
      </QueryProvider>
    )
  });
};

const initializePlaylistConfigEdition = (): void => {
  const store = createStore();

  cy.fixture(`Dashboards/dashboards.json`).then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.interceptAPIRequest({
    alias: 'putPlaylistConfig',
    method: Method.PUT,
    path: `**${playlistEndpoint(1)}`,
    statusCode: 204
  });

  store.set(playlistConfigInitialValuesAtom, {
    dashboards: [
      {
        id: 1,
        name: 'Dashboard 1',
        order: 1
      },
      {
        id: 2,
        name: 'Dashboard 2',
        order: 2
      }
    ],
    description: 'Description',
    id: 1,
    isPublic: false,
    name: 'Playlist',
    rotationTime: 10
  });

  cy.mount({
    Component: (
      <QueryProvider>
        <Provider store={store}>
          <PlaylistConfig />
        </Provider>
      </QueryProvider>
    )
  });
};

describe('Playlist Configuration: creation', () => {
  beforeEach(initializePlaylistConfigCreation);

  it('displays the creation form', () => {
    cy.contains(labelPlaylistProperties).should('be.visible');
    cy.findByLabelText(labelPlaylistName).should('be.visible');
    cy.findByLabelText(labelDescription).should('be.visible');
    cy.contains(labelSelectDashboards).should('be.visible');
    cy.findAllByLabelText(labelAddADashboard).should('have.length', 2);
    cy.findAllByLabelText(labelAddADashboard).eq(1).should('be.disabled');
    cy.findByTestId(labelRotationTime).find('input').should('be.disabled');
    cy.findByLabelText(labelCancel).should('be.enabled');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('sends the playlist configuration when required fields are fullfilled and the corresponding button is clicked', () => {
    cy.findByLabelText(labelPlaylistName).type('New playlist');

    cy.findAllByLabelText(labelAddADashboard).eq(0).click();

    cy.waitForRequest('@getDashboards');

    cy.contains('My Dashboard').click();
    cy.findAllByLabelText(labelAddADashboard).eq(1).click();
    cy.findAllByLabelText(labelAddADashboard).eq(0).click();
    cy.contains('My Dashboard 2').click();
    cy.findAllByLabelText(labelAddADashboard).eq(1).click();

    cy.findByTestId(labelRotationTime).type('{selectall}31');

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@postPlaylistConfig').then(({ request }) => {
      expect(request.body).to.equal(
        '{"dashboards":[{"id":1,"order":1},{"id":2,"order":2}],"description":null,"is_public":false,"name":"New playlist","rotation_time":31}'
      );
    });
  });

  it('does not send the playlist configuration when the name is missing', () => {
    cy.findByLabelText(labelPlaylistName).click();
    cy.findByLabelText(labelDescription).click();

    cy.contains(labelSave).should('be.disabled');
    cy.contains(labelRequired).should('be.visible');

    cy.makeSnapshot();
  });

  it('does not send the playlist configuration when the rotation time is outside the boundary', () => {
    cy.findByLabelText(labelPlaylistName).type('Playlist name');

    cy.findAllByLabelText(labelAddADashboard).eq(0).click();
    cy.contains('My Dashboard').click();
    cy.findAllByLabelText(labelAddADashboard).eq(1).click();
    cy.findAllByLabelText(labelAddADashboard).eq(0).click();
    cy.contains('My Dashboard 2').click();
    cy.findAllByLabelText(labelAddADashboard).eq(1).click();

    cy.findByTestId(labelRotationTime).type('{selectall}61');

    cy.contains(labelSave).should('be.disabled');
    cy.contains('must be less than or equal to 60').should('be.visible');

    cy.findByTestId(labelRotationTime).type('{selectall}8');

    cy.contains(labelSave).should('be.disabled');
    cy.contains('must be greater than or equal to 10').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a modal when the form is updated and the cancel button is clicked', () => {
    cy.findByLabelText(labelPlaylistName).type('playlist');
    cy.contains(labelCancel).click();

    cy.contains('Do you want to save the changes?').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Playlist Configuration: edition', () => {
  beforeEach(initializePlaylistConfigEdition);

  it('displays the form with prefilled values', () => {
    cy.findByLabelText(labelPlaylistName).should('have.value', 'Playlist');
    cy.findByLabelText(labelDescription).should('have.value', 'Description');
    cy.findByLabelText('sort-1').should('be.visible');
    cy.findByLabelText('sort-2').should('be.visible');
    cy.contains('Dashboard 1').should('be.visible');
    cy.contains('Dashboard 2').should('be.visible');
    cy.findByTestId(labelRotationTime).find('input').should('have.value', '10');
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('sends the updated playlist configuration when some fields are changed', () => {
    cy.findByLabelText(labelPlaylistName).type('updated');

    cy.findByLabelText('delete-1').click();

    cy.makeSnapshot();

    cy.contains(labelSave).click();

    cy.waitForRequest('@putPlaylistConfig').then(({ request }) => {
      expect(request.body).to.equal(
        '{"dashboards":[{"id":2,"order":2}],"description":"Description","is_public":false,"name":"Playlistupdated","rotation_time":10}'
      );
    });
  });

  it('displays a modal when the form is updated and the cancel button is clicked', () => {
    cy.findByLabelText(labelPlaylistName).type('updated');
    cy.contains(labelCancel).click();

    cy.contains('Do you want to save the changes?').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a modal when the form is updated with errors and the cancel button is clicked', () => {
    cy.findByLabelText(labelPlaylistName).clear();
    cy.contains(labelCancel).click();

    cy.contains('Do you want to resolve the errors?').should('be.visible');

    cy.makeSnapshot();
  });
});
