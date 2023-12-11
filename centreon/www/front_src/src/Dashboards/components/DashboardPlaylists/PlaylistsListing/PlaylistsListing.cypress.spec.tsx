import { BrowserRouter } from 'react-router-dom';
import { createStore, Provider } from 'jotai';
import { difference } from 'ramda';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  ListingVariant,
  userAtom,
  DashboardGlobalRole
} from '@centreon/ui-context';

import DashboardPlaylistsOverview from '../DashboardPlaylistsOverview';
import { playlistsEndpoint } from '../../../api/endpoints';
import { labelWelcomeToThePlaylistInterface } from '../../../translatedLabels';

import {
  labelDelete,
  labelMoreActions,
  labelPublishYourPlaylist
} from './translatedLabels';
import { buildlistPlaylistsEndpoint } from './api';

const editorRole = {
  canAdministrateDashboard: false,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.creator
};

const viewerRole = {
  canAdministrateDashboard: false,
  canCreateDashboard: false,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.viewer
};

const administratorRole = {
  canAdministrateDashboard: true,
  canCreateDashboard: true,
  canViewDashboard: true,
  globalRole: DashboardGlobalRole.administrator
};

const defaultColumns = [
  'Name',
  'Shares',
  'Role',
  'Description',
  'Rotation time',
  'Creator',
  'Creation date',
  'Update',
  'Actions',
  'Public link',
  'Private/Public'
];

const viewerColumns = [
  'Name',
  'Description',
  'Rotation time',
  'Creator',
  'Creation date',
  'Update',
  'Public link'
];

const columnToSort = [
  { id: 'name', label: 'Name' },
  { id: 'author', label: 'Creator' },
  { id: 'created_at', label: 'Creation date' },
  { id: 'updated_at', label: 'Update' }
];

const defaultQueryParams = {
  limit: 10,
  page: 1,
  search: {
    regex: {
      fields: ['name'],
      value: ''
    }
  },
  sort: { name: 'asc' },
  total: 6
};

const store = createStore();

const initializeAndMount = ({
  emptyList = false,
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true,
  responseFile = 'playlistsAdminUser',
  endpoint = `${playlistsEndpoint}?**`
}): void => {
  store.set(userAtom, {
    alias: 'admin',
    dashboard: {
      createDashboards: canCreateDashboard,
      globalUserRole: globalRole,
      manageAllDashboards: canAdministrateDashboard,
      viewDashboards: canViewDashboard
    },
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris',
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  });

  cy.viewport('macbook-13');
  cy.fixture(
    `Dashboards/Playlists/${emptyList ? 'emptyPlaylists' : responseFile}.json`
  ).then((playlists) => {
    cy.interceptAPIRequest({
      alias: 'getplaylists',
      method: Method.GET,
      path: endpoint,
      response: playlists
    });
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <BrowserRouter>
          <SnackbarProvider>
            <Provider store={store}>
              <DashboardPlaylistsOverview />
            </Provider>
          </SnackbarProvider>
        </BrowserRouter>
      </TestQueryProvider>
    )
  });
};

describe('User', () => {
  describe('Admin', () => {
    it('displays a welcome label and create button when the playlists listing is empty', () => {
      initializeAndMount({
        ...administratorRole,
        emptyList: true
      });

      cy.waitForRequest('@getplaylists');

      cy.contains(labelWelcomeToThePlaylistInterface).should('be.visible');
      cy.findByLabelText('create').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays all default columns', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getplaylists');

      defaultColumns.map((columnName) =>
        cy.findByText(columnName).should('be.visible')
      );

      cy.makeSnapshot();
    });

    it('displays the first page of the playlist listing', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getplaylists');

      cy.findByText('Dashboard Playlist 1').should('be.visible');
      cy.findByText('Dashboard Playlist 2').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays shared contact and contact group when expand shares button was clicked', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getplaylists');

      cy.findAllByTestId('ExpandMoreIcon').eq(0).click();

      cy.findByText('Steven').should('be.visible');
      cy.findByText('Harry').should('be.visible');
      cy.findByText('Developers').should('be.visible');
      cy.findByText('Managers').should('be.visible');

      cy.findAllByTestId('PersonRemoveIcon').should('have.length', 4);

      cy.makeSnapshot();

      cy.findAllByTestId('ExpandMoreIcon').eq(0).click();
    });

    it("dispalys publish and delete action when 'more actions' button was clicked ", () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getplaylists');

      cy.findAllByTestId(labelMoreActions).eq(0).click();

      cy.findByText(labelPublishYourPlaylist).should('be.visible');
      cy.findByText(labelDelete).should('be.visible');

      cy.makeSnapshot();
    });
    it('diplays the playlist description when description icon was hovered', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getplaylists');

      cy.findAllByTestId('DescriptionOutlinedIcon').should('have.length', 6);
      cy.findAllByTestId('DescriptionOutlinedIcon').eq(0).trigger('mouseover');

      cy.findByText('Sample description for Playlist 1').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Editor', () => {
    it('displays a welcome label and create button when the playlists listing is empty', () => {
      initializeAndMount({
        ...editorRole,
        emptyList: true
      });

      cy.waitForRequest('@getplaylists');

      cy.contains(labelWelcomeToThePlaylistInterface).should('be.visible');
      cy.findByLabelText('create').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays all default columns', () => {
      initializeAndMount(editorRole);

      cy.waitForRequest('@getplaylists');

      defaultColumns.map((columnName) =>
        cy.findByText(columnName).should('be.visible')
      );

      cy.makeSnapshot();
    });

    it('displays the first page of the playlist listing', () => {
      initializeAndMount(editorRole);

      cy.waitForRequest('@getplaylists');

      cy.findByText('Dashboard Playlist 1').should('be.visible');
      cy.findByText('Dashboard Playlist 2').should('be.visible');

      cy.makeSnapshot();
    });

    it('replaces the editor row content by "-" when the current user has not edition role on the playlist', () => {
      initializeAndMount({ ...editorRole, responseFile: 'playlists' });

      cy.waitForRequest('@getplaylists');

      cy.findAllByTestId('ExpandMoreIcon').should('have.length', 4);
      cy.findAllByTestId('Share').should('have.length', 4);
      cy.findAllByTestId('SettingsOutlinedIcon').should('have.length', 4);
      cy.findAllByTestId('More actions').should('have.length', 4);
      cy.findAllByTestId('Private/Public').should('have.length', 4);

      cy.makeSnapshot();
    });
    it('diplays the playlist description when description icon was hovered', () => {
      initializeAndMount({ ...editorRole, responseFile: 'playlists' });

      cy.waitForRequest('@getplaylists');

      cy.findAllByTestId('DescriptionOutlinedIcon').should('have.length', 6);
      cy.findAllByTestId('DescriptionOutlinedIcon').eq(0).trigger('mouseover');

      cy.findByText('Sample description for Playlist 1').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Viewer', () => {
    it('displays a welcome label when the playlists listing is empty', () => {
      initializeAndMount({
        ...viewerRole,
        emptyList: true
      });

      cy.waitForRequest('@getplaylists');

      cy.contains(labelWelcomeToThePlaylistInterface).should('be.visible');

      cy.makeSnapshot();
    });
    it("don't display the create button when the playlists listing is empty", () => {
      initializeAndMount({
        ...viewerRole,
        emptyList: true
      });

      cy.waitForRequest('@getplaylists');

      cy.findByLabelText('create').should('not.exist');

      cy.makeSnapshot();
    });
    it('displays viewer columns only', () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getplaylists');

      viewerColumns.map((columnName) =>
        cy.findByText(columnName).should('be.visible')
      );

      difference(viewerColumns, defaultColumns).map((columnName) =>
        cy.findByText(columnName).should('not.exist')
      );

      cy.makeSnapshot();
    });

    it('displays the first page of the playlist listing', () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getplaylists');

      cy.findByText('Dashboard Playlist 1').should('be.visible');
      cy.findByText('Dashboard Playlist 2').should('be.visible');

      cy.makeSnapshot();
    });
    it('displays the playlist description directly instead of using an icon and tooltip', () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getplaylists');

      cy.findByTestId('DescriptionOutlinedIcon').should('not.exist');

      cy.findByText('Sample description for Playlist 1').should('be.visible');

      cy.makeSnapshot();
    });
  });
});

const initializeSortingAndMount = (): void => {
  columnToSort.forEach(({ id: sortBy, label }) => {
    const requestEndpointDesc = buildlistPlaylistsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'desc'
      }
    });

    cy.fixture(`Dashboards/Playlists/playlists.json`).then((playlists) => {
      cy.interceptAPIRequest({
        alias: `getplaylists/${label}/desc`,
        method: Method.GET,
        path: requestEndpointDesc,
        response: playlists
      });
    });

    const requestEndpointAsc = buildlistPlaylistsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'asc'
      }
    });

    cy.fixture(`Dashboards/Playlists/playlists.json`).then((playlists) => {
      cy.interceptAPIRequest({
        alias: `getplaylists/${label}/asc`,
        method: Method.GET,
        path: requestEndpointAsc,
        response: playlists
      });
    });
  });

  initializeAndMount(viewerRole);
};

describe('column sorting', () => {
  it('executes a listing request when a sortable column is clicked', () => {
    initializeSortingAndMount();

    columnToSort.forEach(({ id: sortBy, label }) => {
      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `getplaylists/${label}/desc`
      });

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `getplaylists/${label}/asc`
      });

      cy.contains('Dashboard Playlist 1').should('be.visible');

      cy.makeSnapshot(
        `column sorting --  executes a listing request when the ${label} column is clicked`
      );
    });
  });
});
