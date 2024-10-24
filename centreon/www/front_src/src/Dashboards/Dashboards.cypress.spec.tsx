import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { Method } from '@centreon/js-config/cypress/component/commands';
import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  DashboardGlobalRole,
  ListingVariant,
  platformVersionsAtom,
  userAtom
} from '@centreon/ui-context';

import { labelMoreActions } from '../Resources/translatedLabels';

import { DashboardsPage } from './DashboardsPage';
import {
  dashboardSharesEndpoint,
  dashboardsContactsEndpoint,
  dashboardsEndpoint,
  getDashboardAccessRightsContactGroupEndpoint,
  getDashboardEndpoint,
  playlistsByDashboardEndpoint
} from './api/endpoints';
import { DashboardRole } from './api/models';
import { viewModeAtom } from './components/DashboardLibrary/DashboardListing/atom';
import { ViewMode } from './components/DashboardLibrary/DashboardListing/models';
import {
  labelCardsView,
  labelEditProperties,
  labelEditor,
  labelListView,
  labelViewer
} from './components/DashboardLibrary/DashboardListing/translatedLabels';
import { DashboardLayout } from './models';
import { routerHooks } from './routerHooks';
import {
  labelAddAContact,
  labelCancel,
  labelCreate,
  labelDashboardDeleted,
  labelDashboardDuplicated,
  labelDashboardUpdated,
  labelDelete,
  labelDeleteDashboard,
  labelDeleteUser,
  labelDescription,
  labelDuplicate,
  labelDuplicateDashboard,
  labelName,
  labelSave,
  labelSaveYourDashboardForThumbnail,
  labelShareWithContacts,
  labelSharesSaved,
  labelUpdate,
  labelUserDeleted,
  labelWelcomeToDashboardInterface
} from './translatedLabels';

interface InitializeAndMountProps {
  canAdministrateDashboard?: boolean;
  canCreateDashboard?: boolean;
  canViewDashboard?: boolean;
  emptyList?: boolean;
  globalRole?: DashboardGlobalRole;
  layout?: DashboardLayout;
  ownRole?: DashboardRole;
}

const initializeAndMount = ({
  globalRole = DashboardGlobalRole.administrator,
  canCreateDashboard = true,
  canViewDashboard = true,
  canAdministrateDashboard = true,
  emptyList,
  layout = DashboardLayout.Library
}: InitializeAndMountProps): {
  navigate;
  store;
} => {
  const store = createStore();

  store.set(viewModeAtom, ViewMode.List);

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

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.viewport('macbook-13');

  cy.fixture(
    `Dashboards/${emptyList ? 'emptyDashboards' : 'dashboards'}.json`
  ).then((dashboards) => {
    cy.interceptAPIRequest({
      alias: 'getDashboards',
      method: Method.GET,
      path: `${dashboardsEndpoint}?**`,
      response: dashboards
    });
  });

  cy.fixture('Dashboards/Dashboard/details.json').then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: getDashboardEndpoint('1'),
      response: dashboardDetails
    });
  });

  cy.fixture('Dashboards/contacts.json').then((response) => {
    cy.interceptAPIRequest({
      alias: 'getContacts',
      method: Method.GET,
      path: `./api/latest${dashboardsContactsEndpoint}?**`,
      response
    });
  });

  cy.interceptAPIRequest({
    alias: 'createDashboard',
    method: Method.POST,
    path: dashboardsEndpoint,
    response: {
      created_at: '',
      created_by: {
        id: 1,
        name: 'User 1'
      },
      description: null,
      id: 1,
      name: 'My Dashboard',
      own_role: 'editor',
      updated_at: '',
      updated_by: {
        id: 1,
        name: 'User 1'
      }
    },
    statusCode: 201
  });

  cy.interceptAPIRequest({
    alias: 'deleteDashboard',
    method: Method.DELETE,
    path: `${dashboardsEndpoint}/1`,
    statusCode: 204
  });

  cy.interceptAPIRequest({
    alias: 'updateDashboard',
    method: Method.POST,
    path: `${dashboardsEndpoint}/1`,
    statusCode: 204
  });

  cy.interceptAPIRequest({
    alias: 'putShares',
    method: Method.PUT,
    path: `./api/latest${dashboardSharesEndpoint(1)}`,
    statusCode: 204
  });
  cy.interceptAPIRequest({
    alias: 'revokeUser',
    method: Method.DELETE,
    path: getDashboardAccessRightsContactGroupEndpoint(1, 3)
  });

  const version = {
    fix: '0',
    major: '0',
    minor: '0',
    version: '1.0.0'
  };
  store.set(platformVersionsAtom, {
    modules: {
      'centreon-it-edition-extensions': version
    },
    web: version,
    widgets: {}
  });
  cy.interceptAPIRequest({
    alias: 'getPlaylistsByDashboard',
    method: Method.GET,
    path: `./api/latest${playlistsByDashboardEndpoint(1)}`,
    response: [{ id: 1, name: 'playlist' }]
  });

  cy.stub(routerHooks, 'useParams').returns({
    layout
  });

  const navigate = cy.stub();
  cy.stub(routerHooks, 'useNavigate').returns(navigate);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <BrowserRouter>
            <Provider store={store}>
              <DashboardsPage />
            </Provider>
          </BrowserRouter>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });

  return {
    navigate,
    store
  };
};

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

const columns = [
  'Name',
  'Description',
  'Creation date',
  'Last update',
  'Actions'
];

describe('Dashboards', () => {
  describe('Overview', () => {
    it('displays a welcome label when the dashboard library is empty', () => {
      initializeAndMount({
        ...administratorRole,
        emptyList: true
      });

      cy.contains(labelWelcomeToDashboardInterface).should('be.visible');
      cy.findByLabelText('create').should('be.visible');

      cy.makeSnapshot();
    });

    it('creates a dashboard when the corresponding button is clicked and the title is filled', () => {
      initializeAndMount({
        ...administratorRole,
        emptyList: true
      });

      cy.findByLabelText('create').click();

      cy.findByLabelText(labelName).type('My Dashboard');

      cy.makeSnapshot();

      cy.viewport('macbook-13');

      cy.findByLabelText(labelCreate).click();
      cy.waitForRequest('@createDashboard');
      cy.url().should(
        'equal',
        'http://localhost:9092/home/dashboards/library/1?edit=true'
      );
    });
  });

  describe('View mode', () => {
    it('displays the dashboards in "View By lists" by default', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelListView).should(
        'have.attr',
        'data-selected',
        'true'
      );
      cy.findByTestId(labelCardsView).should(
        'have.attr',
        'data-selected',
        'false'
      );

      cy.contains('My Dashboard').should('be.visible');
      cy.contains('My Dashboard 2').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the dashboards in "View as card" when the corresponding button is clicked', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelCardsView).click();

      cy.findByTestId(labelCardsView).should(
        'have.attr',
        'data-selected',
        'true'
      );

      cy.contains('My Dashboard').should('be.visible');
      cy.findByTestId('thumbnail-My Dashboard-my description').should(
        'be.visible'
      );
      cy.contains('My Dashboard 2').should('be.visible');
      cy.findByTestId('thumbnail-My Dashboard 2-undefined').should(
        'be.visible'
      );

      cy.findAllByTestId('thumbnail-fallback').first().trigger('mouseover');

      cy.contains(labelSaveYourDashboardForThumbnail).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays pagination in both view modes', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelCardsView).click();

      cy.findByTestId('Listing Pagination').should('be.visible');

      cy.findByTestId(labelListView).click();

      cy.findByTestId('Listing Pagination').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays column configuration button only in "View as list"', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelCardsView).click();

      cy.findByTestId('ViewColumnIcon').should('not.exist');

      cy.findByTestId(labelListView).click();

      cy.findByTestId('ViewColumnIcon').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Roles', () => {
    it('displays the dashboard actions on the corresponding dashboard when the user has editor roles', () => {
      initializeAndMount(editorRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('Add').should('be.visible');

      cy.findAllByLabelText(labelMoreActions).eq(0).should('be.visible');

      cy.makeSnapshot();
    });

    it("doesn't displays the dashboard actions when the user has viewer roles", () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('add').should('not.exist');

      cy.findByLabelText(labelShareWithContacts).should('not.exist');
      cy.findByLabelText(labelMoreActions).should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the dashboards actions on all dashboards when the user has administrator global roles', () => {
      initializeAndMount(administratorRole);

      cy.waitForRequest('@getDashboards');

      cy.findByLabelText('Add').should('be.visible');

      cy.findAllByLabelText(labelMoreActions).should('have.length', 2);
      cy.findAllByLabelText(labelShareWithContacts).should('have.length', 2);

      cy.makeSnapshot();
    });

    it('displays all dashboard columns in the "View as list" when the user has editor global roles', () => {
      initializeAndMount(editorRole);

      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelListView).click();

      columns.forEach((column) => {
        cy.findByText(column);
      });

      cy.makeSnapshot();
    });

    it('does not display actions in the "View as list" when the user has viewer global role', () => {
      initializeAndMount(viewerRole);

      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelListView).click();

      cy.findByText('Actions').should('not.exist');

      cy.findByLabelText('Add').should('not.exist');

      cy.makeSnapshot();
    });
  });

  describe('Shares', () => {
    it('displays shares when a row is expanded', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelListView).click();

      cy.contains('2 shares').should('be.visible');

      cy.findByTestId('ExpandMoreIcon').click();
      cy.contains('Kevin').should('be.visible');
      cy.findByLabelText(labelViewer).should('be.visible');
      cy.findByLabelText(labelEditor).should('be.visible');
      cy.findByTestId('PeopleIcon').should('be.visible');

      cy.makeSnapshot();
    });

    it('revokes the access right when a row is expanded and the corresponding action is clicked ', () => {
      initializeAndMount(administratorRole);
      cy.waitForRequest('@getDashboards');

      cy.findByTestId(labelListView).click();

      cy.contains('2 shares').should('be.visible');

      cy.findByTestId('ExpandMoreIcon').click();

      cy.findAllByTestId('PersonRemoveIcon').eq(1).click();

      cy.contains(labelDeleteUser).should('be.visible');
      cy.get(`[aria-label="${labelDelete}"][data-is-danger="true"]`).click();

      cy.waitForRequest('@revokeUser');

      cy.contains(labelUserDeleted).should('be.visible');

      cy.makeSnapshot();
    });
  });

  [labelListView, labelCardsView].forEach((displayView) => {
    describe(`Actions: ${displayView}`, () => {
      it('deletes a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelDelete).click();
        cy.contains(
          'The My Dashboard dashboard is part of one or several playlists. It will be permanently deleted from any playlists it belongs to.'
        ).should('be.visible');

        cy.findByLabelText(labelDelete).click();

        cy.waitForRequest('@deleteDashboard');

        cy.contains(labelDashboardDeleted).should('be.visible');

        cy.makeSnapshot(
          `${displayView}: deletes a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.`
        );
      });

      it('does not delete a dashboard upon clicking the corresponding icon button and cancelling the action by clicking the cancellation button."    ', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelDelete).click();

        cy.contains(labelCancel).click();

        cy.contains(labelCancel).should('not.exist');
        cy.contains(labelDeleteDashboard).should('not.exist');

        cy.makeSnapshot(
          `${displayView}: does not delete a dashboard upon clicking the corresponding icon button and cancelling the action by clicking the cancellation button.`
        );
      });

      it('duplicates a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelDuplicate).click();
        cy.waitForRequest('@getDashboardDetails');

        cy.findByLabelText(labelName).should('have.value', 'My Dashboard_1');

        cy.findByLabelText(labelName).clear().type('new name');

        cy.findByLabelText(labelDuplicate).click();

        cy.waitForRequest('@createDashboard').then(({ request }) => {
          expect(JSON.parse(request.body).name).to.be.equal('new name');
        });

        cy.contains(labelDashboardDuplicated).should('be.visible');

        cy.makeSnapshot(
          `${displayView}: duplicates a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.`
        );
      });

      it('does not duplicate a dashboard upon clicking the corresponding icon button and cancelling the action by clicking the cancellation button."    ', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelDuplicate).click();

        cy.contains(labelCancel).click();

        cy.contains(labelCancel).should('not.exist');
        cy.contains(labelDuplicateDashboard).should('not.exist');

        cy.makeSnapshot(
          `${displayView}: does not duplicate a dashboard upon clicking the corresponding icon button and cancelling the action by clicking the cancellation button.`
        );
      });

      it('disables confirm duplication button when the new name is less than three characters.', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelDuplicate).click();
        cy.waitForRequest('@getDashboardDetails');

        cy.findByLabelText(labelName).should('have.value', 'My Dashboard_1');
        cy.findByLabelText(labelName).clear().type('ab');

        cy.findByLabelText(labelDuplicate).should('be.disabled');

        cy.makeSnapshot(
          `${displayView}: disables confirm duplication button when the new name is less than three characters.`
        );
      });

      it('edits a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelEditProperties).click();

        cy.findByLabelText(labelName).should('have.value', 'My Dashboard');
        cy.findByLabelText(labelDescription).should(
          'have.value',
          'my description'
        );

        cy.findByLabelText(labelUpdate).should('be.disabled');

        cy.findByLabelText(labelName).clear().type('New name');
        cy.findByLabelText(labelDescription).clear().type('New description');

        cy.findByLabelText(labelUpdate).click();

        cy.waitForRequest('@updateDashboard').then(({ request }) => {
          const formData = new URLSearchParams(request.body);

          const formDataObj = {};
          formData.forEach((value, key) => {
            formDataObj[key] = value;
          });

          expect(formDataObj).to.deep.equal({
            description: 'New description',
            name: 'New name'
          });
        });

        cy.contains(labelDashboardUpdated).should('be.visible');

        cy.makeSnapshot(
          `${displayView}: edits a dashboard upon clicking the corresponding icon button and confirming the action by clicking the confirmation button.`
        );
      });

      it('disables confirm update button when the new name is less than three characters.', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByLabelText('More actions').eq(0).click();

        cy.findByLabelText(labelEditProperties).click();

        cy.findByLabelText(labelName).clear().type('ab');

        cy.findByLabelText(labelUpdate).should('be.disabled');

        cy.makeSnapshot(
          `${displayView}: disables confirm update button when the new name is less than three characters.`
        );
      });

      it('sends a shares update request when the shares are updated and the corresponding button is clicked', () => {
        initializeAndMount(administratorRole);

        cy.findByLabelText(displayView).click();

        cy.findAllByTestId(labelShareWithContacts).eq(0).click();

        cy.findByLabelText(labelAddAContact).click();

        cy.waitForRequest('@getContacts');

        cy.contains(/^User$/)
          .parent()
          .click();

        cy.findByTestId('add').click();

        cy.contains(labelSave).click();

        cy.waitForRequest('@putShares');

        cy.contains(labelSharesSaved).should('be.visible');

        cy.makeSnapshot(
          `${displayView}: sends a shares update request when the shares are updated and the corresponding button is clicked`
        );
      });
    });
  });

  it('creates a dashboard when the corresponding button is clicked and the title is filled', () => {
    initializeAndMount({
      ...administratorRole,
      emptyList: true
    });

    cy.findByLabelText('create').click();

    cy.findByLabelText(labelName).type('My Dashboard');

    cy.makeSnapshot();

    cy.viewport('macbook-13');

    cy.findByLabelText(labelCreate).click();
    cy.waitForRequest('@createDashboard');
    cy.url().should(
      'equal',
      'http://localhost:9092/home/dashboards/library/1?edit=true'
    );
  });

  it('deletes a dashboard when the corresponding icon button is clicked and the confirmation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.findByLabelText(labelDelete).click();
    cy.findAllByLabelText(labelDelete).last().click();

    cy.waitForRequest('@deleteDashboard');

    cy.contains(labelDashboardDeleted).should('be.visible');
  });

  it('does not delete a dashboard when the corresponding icon button is clicked and the cancellation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.findByLabelText(labelDelete).click();
    cy.contains(labelCancel).click();

    cy.contains(labelCancel).should('not.exist');
    cy.contains(labelDeleteDashboard).should('not.exist');
  });

  it('deletes a dashboard in the listing view when the corresponding icon button is clicked and the confirmation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findByLabelText(labelListView).click();

    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.findByLabelText(labelDelete).click();
    cy.contains(
      'The My Dashboard dashboard is part of one or several playlists. It will be permanently deleted from any playlists it belongs to.'
    ).should('be.visible');
    cy.findAllByLabelText(labelDelete).last().click();

    cy.waitForRequest('@deleteDashboard');

    cy.contains(labelDashboardDeleted).should('be.visible');
  });

  it('does not delete a dashboard in the listing view when the corresponding icon button is clicked and the cancellation button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findByLabelText(labelListView).click();

    cy.findAllByLabelText(labelMoreActions).eq(0).click();
    cy.findByLabelText(labelDelete).click();

    cy.waitForRequest('@getPlaylistsByDashboard');

    cy.contains(labelDeleteDashboard).should('be.visible');
    cy.contains(
      'The My Dashboard dashboard is part of one or several playlists. It will be permanently deleted from any playlists it belongs to.'
    ).should('be.visible');

    cy.contains(labelCancel).click();

    cy.contains(labelCancel).should('not.exist');
    cy.contains(labelDeleteDashboard).should('not.exist');
  });

  it('sends a shares update request when the shares are updated and the corresponding button is clicked', () => {
    initializeAndMount(administratorRole);

    cy.findAllByTestId(labelShareWithContacts).eq(0).click();

    cy.findByLabelText(labelAddAContact).click();

    cy.waitForRequest('@getContacts');

    cy.contains(/^User$/)
      .parent()
      .click();

    cy.findByTestId('add').click();

    cy.contains(labelSave).click();

    cy.waitForRequest('@putShares');

    cy.contains(labelSharesSaved).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Navigation to dashboard', () => {
    it('navigates to the dashboard page when the listing mode is activated and a row is clicked', () => {
      const { navigate } = initializeAndMount({
        ...administratorRole
      });

      cy.findByTestId('View as list').click();

      cy.contains('Arnaud')
        .click()
        .then(() => {
          expect(navigate).to.be.calledWith('/home/dashboards/library/1');
        });

      cy.makeSnapshot();
    });

    it('does not navigate to the dashboard page when the listing mode is activated and a row is clicked on the actions cell', () => {
      const { navigate } = initializeAndMount({
        ...administratorRole
      });

      cy.findByTestId('View as list').click();

      cy.contains('Arnaud').should('be.visible');

      cy.get('.MuiTableRow-root')
        .first()
        .get('.MuiTableCell-body')
        .last()
        .click('topLeft')
        .then(() => {
          // eslint-disable-next-line @typescript-eslint/no-unused-expressions
          expect(navigate).to.not.be.called;
        });

      cy.makeSnapshot();
    });
  });
});
