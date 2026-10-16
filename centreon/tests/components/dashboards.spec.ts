import type { Page } from '@playwright/test';

import contacts from '../../cypress/fixtures/Dashboards/contacts.json';
import details from '../../cypress/fixtures/Dashboards/Dashboard/details.json';
import dashboards from '../../cypress/fixtures/Dashboards/dashboards.json';
import emptyDashboards from '../../cypress/fixtures/Dashboards/emptyDashboards.json';
import listAddedOne from '../../cypress/fixtures/Dashboards/favorites/listing/afterAddAction/listOne.json';
import listAddedTwo from '../../cypress/fixtures/Dashboards/favorites/listing/afterAddAction/listTwo.json';
import listRemovedOne from '../../cypress/fixtures/Dashboards/favorites/listing/afterDeleteAction/listOne.json';
import listRemovedTwo from '../../cypress/fixtures/Dashboards/favorites/listing/afterDeleteAction/listTwo.json';
import favoritesList from '../../cypress/fixtures/Dashboards/favorites/listing/list.json';
import allFavorites from '../../cypress/fixtures/Dashboards/favorites/listing/listAllMarkedFavorite.json';
import {
  labelCardsView,
  labelEditor,
  labelEditProperties,
  labelListView,
  labelViewer
} from '../../www/front_src/src/Dashboards/components/DashboardLibrary/DashboardListing/translatedLabels';
import {
  labelAddAContact,
  labelAddToFavorites,
  labelCancel,
  labelCreate,
  labelDashboardAddedToFavorites,
  labelDashboardDeleted,
  labelDashboardDuplicated,
  labelDashboardRemovedFromFavorites,
  labelDashboardUpdated,
  labelDelete,
  labelDeleteDashboard,
  labelDeleteUser,
  labelDescription,
  labelDuplicate,
  labelDuplicateDashboard,
  labelName,
  labelRemoveFromFavorites,
  labelSave,
  labelSaveYourDashboardForThumbnail,
  labelSharesSaved,
  labelShareWithContacts,
  labelUpdate,
  labelUserDeleted,
  labelWelcomeToDashboardInterface
} from '../../www/front_src/src/Dashboards/translatedLabels';
import { labelMoreActions } from '../../www/front_src/src/Resources/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/Dashboards.
const storyId = 'Dashboards/DashboardsPage/Default';
const endpoint = './api/latest/configuration/dashboards';
const playlistWarning =
  'The My Dashboard dashboard is part of one or several playlists. It will be permanently deleted from any playlists it belongs to.';

type Mount = (id: string, props?: object) => Promise<unknown>;
type Role = 'administrator' | 'editor' | 'viewer';

interface Mocks {
  addFavorite: MockedEndpoint;
  create: MockedEndpoint;
  deleteDashboard: MockedEndpoint;
  favorites: MockedEndpoint;
  listing: MockedEndpoint;
  revoke: MockedEndpoint;
  shares: MockedEndpoint;
  update: MockedEndpoint;
}

let mocks: Mocks;

const setUp = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  { listing = dashboards as unknown, role = 'administrator' as Role } = {}
): Promise<void> => {
  await mockApi({ endpoint: `${endpoint}/1`, response: details });
  await mockApi({
    endpoint: 'configuration/dashboards/contacts',
    response: contacts
  });
  await mockApi({
    endpoint: 'configuration/dashboards/1/playlists',
    response: [{ id: 1, name: 'playlist' }]
  });
  mocks = {
    addFavorite: await mockApi({
      endpoint: 'configuration/dashboards/favorites',
      method: 'POST',
      status: 204
    }),
    create: await mockApi({
      endpoint,
      method: 'POST',
      response: {
        created_at: '',
        created_by: { id: 1, name: 'User 1' },
        description: null,
        id: 1,
        name: 'My Dashboard',
        own_role: 'editor',
        updated_at: '',
        updated_by: { id: 1, name: 'User 1' }
      },
      status: 201
    }),
    deleteDashboard: await mockApi({
      endpoint: `${endpoint}/1`,
      method: 'DELETE',
      status: 204
    }),
    favorites: await mockApi({
      endpoint: 'configuration/dashboards/favorites',
      response: allFavorites
    }),
    listing: await mockApi({ endpoint, response: listing }),
    revoke: await mockApi({
      endpoint: 'configuration/dashboards/1/access_rights/contactgroups/3',
      method: 'DELETE'
    }),
    shares: await mockApi({
      endpoint: 'configuration/dashboards/1/shares',
      method: 'PUT',
      status: 204
    }),
    update: await mockApi({
      endpoint: `${endpoint}/1`,
      method: 'POST',
      status: 204
    })
  };
  await mount(storyId, { role });
};

test.use({ viewport: { height: 800, width: 1280 } });

const location = (page: Page) => page.getByTestId('location');
const moreActions = (page: Page) =>
  page.getByLabel(labelMoreActions, { exact: true });
const nameField = (page: Page) => page.getByLabel(labelName, { exact: true });
const button = (page: Page, label: string) =>
  page.getByLabel(label, { exact: true });

const expectListing = async (page: Page): Promise<void> => {
  await expect(page.getByText('My Dashboard 2').first()).toBeVisible();
};

test.describe('Overview', () => {
  test('displays a welcome label when the library is empty', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount }, { listing: emptyDashboards });
    await expect(
      page.getByText(labelWelcomeToDashboardInterface)
    ).toBeVisible();
    await expect(page.getByLabel('create')).toBeVisible();
    await expectThemedScreenshot(page, 'dashboards-empty');
  });

  test('creates a dashboard and opens it in edition', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount }, { listing: emptyDashboards });
    await page.getByLabel('create').click();
    await nameField(page).fill('My Dashboard');
    await button(page, labelCreate).click();
    await expect.poll(() => mocks.create.bodies.length).toBe(1);
    await expect(location(page)).toHaveValue(
      '/home/dashboards/library/1?edit=true'
    );
  });
});

test.describe('View mode', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await expectListing(page);
  });

  test('lists the dashboards by default', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect(page.getByTestId(labelListView)).toHaveAttribute(
      'data-selected',
      'true'
    );
    await expect(page.getByTestId(labelCardsView)).toHaveAttribute(
      'data-selected',
      'false'
    );
    await expect(page.getByText('My Dashboard', { exact: true })).toBeVisible();
    await expectThemedScreenshot(page, 'dashboards-list');
  });

  test('displays the dashboards as cards', async ({
    expectThemedScreenshot,
    page
  }) => {
    await page.getByTestId(labelCardsView).click();
    await expect(page.getByTestId(labelCardsView)).toHaveAttribute(
      'data-selected',
      'true'
    );
    await expect(
      page.getByTestId('thumbnail-My Dashboard-my description')
    ).toBeVisible();
    await expect(
      page.getByTestId('thumbnail-My Dashboard 2-undefined')
    ).toBeVisible();
    await page
      .getByTestId('thumbnail-fallback')
      .first()
      .dispatchEvent('mouseover');
    await expect(
      page.getByText(labelSaveYourDashboardForThumbnail)
    ).toBeVisible();
    await expectThemedScreenshot(page, 'dashboards-cards');
  });

  test('paginates both views and configures the columns of the list', async ({
    page
  }) => {
    await page.getByTestId(labelCardsView).click();
    await expect(page.getByTestId('Listing Pagination')).toBeVisible();
    await expect(page.getByTestId('ViewColumnIcon')).toHaveCount(0);
    await page.getByTestId(labelListView).click();
    await expect(page.getByTestId('Listing Pagination')).toBeVisible();
    await expect(page.getByTestId('ViewColumnIcon')).toBeVisible();
  });
});

test.describe('Roles', () => {
  test('lets an editor act on the dashboards and see every column', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount }, { role: 'editor' });
    await expectListing(page);
    await expect(button(page, 'Add')).toBeVisible();
    await expect(moreActions(page).first()).toBeVisible();
    for (const column of [
      'Name',
      'Description',
      'Creation date',
      'Last update',
      'Actions'
    ]) {
      await expect(
        page.getByText(column, { exact: true }).first()
      ).toBeVisible();
    }
  });

  test('hides the dashboard actions from a viewer', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount }, { role: 'viewer' });
    await expectListing(page);
    await expect(button(page, 'add')).toHaveCount(0);
    await expect(button(page, 'Add')).toHaveCount(0);
    await expect(page.getByLabel(labelShareWithContacts)).toHaveCount(0);
    await expect(moreActions(page)).toHaveCount(0);
    await expect(page.getByText('Actions', { exact: true })).toHaveCount(0);
    await expectThemedScreenshot(page, 'dashboards-viewer');
  });

  test('lets an administrator act on every dashboard', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListing(page);
    await expect(button(page, 'Add')).toBeVisible();
    await expect(moreActions(page)).toHaveCount(2);
    await expect(page.getByLabel(labelShareWithContacts)).toHaveCount(2);
  });
});

test.describe('Shares', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await page.getByTestId(labelListView).click();
    await expect(page.getByText('2 shares')).toBeVisible();
    await page.getByTestId('ExpandMoreIcon').click();
  });

  test('displays the shares of an expanded row', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect(page.getByText('Kevin')).toBeVisible();
    await expect(page.getByLabel(labelViewer)).toBeVisible();
    await expect(page.getByLabel(labelEditor)).toBeVisible();
    await expect(page.getByTestId('PeopleIcon')).toBeVisible();
    await expectThemedScreenshot(page, 'dashboards-shares');
  });

  test('revokes an access right from an expanded row', async ({ page }) => {
    await page.getByTestId('PersonRemoveIcon').nth(1).click();
    await expect(page.getByText(labelDeleteUser)).toBeVisible();
    await page
      .locator(`[aria-label="${labelDelete}"][data-is-danger="true"]`)
      .click();
    await expect.poll(() => mocks.revoke.urls.length).toBe(1);
    await expect(page.getByText(labelUserDeleted)).toBeVisible();
  });
});

for (const view of [labelListView, labelCardsView]) {
  test.describe(`Actions: ${view}`, () => {
    test.beforeEach(async ({ mockApi, mount, page }) => {
      await setUp({ mockApi, mount });
      await expectListing(page);
      await page.getByLabel(view, { exact: true }).click();
    });

    const openAction = async (page: Page, action: string): Promise<void> => {
      await page.getByLabel('More actions', { exact: true }).first().click();
      await button(page, action).click();
    };

    test('deletes a dashboard once confirmed', async ({
      expectThemedScreenshot,
      page
    }) => {
      await openAction(page, labelDelete);
      await expect(page.getByText(playlistWarning)).toBeVisible();
      await expectThemedScreenshot(
        page.getByRole('dialog'),
        `dashboards-delete-${view === labelListView ? 'list' : 'cards'}`
      );
      await button(page, labelDelete).last().click();
      await expect.poll(() => mocks.deleteDashboard.urls.length).toBe(1);
      await expect(page.getByText(labelDashboardDeleted)).toBeVisible();
    });

    test('keeps a dashboard when the deletion is cancelled', async ({
      page
    }) => {
      await openAction(page, labelDelete);
      await expect(page.getByText(labelDeleteDashboard)).toBeVisible();
      await page.getByText(labelCancel, { exact: true }).click();
      await expect(page.getByText(labelDeleteDashboard)).toHaveCount(0);
      expect(mocks.deleteDashboard.urls).toHaveLength(0);
    });

    test('duplicates a dashboard under a new name', async ({ page }) => {
      await openAction(page, labelDuplicate);
      await expect(nameField(page)).toHaveValue('My Dashboard_1');
      await nameField(page).fill('new name');
      await button(page, labelDuplicate).last().click();
      await expect
        .poll(() => (mocks.create.bodies[0] as { name?: string })?.name)
        .toBe('new name');
      await expect(page.getByText(labelDashboardDuplicated)).toBeVisible();
    });

    test('keeps the dashboards when the duplication is cancelled', async ({
      page
    }) => {
      await openAction(page, labelDuplicate);
      await expect(page.getByText(labelDuplicateDashboard)).toBeVisible();
      await page.getByText(labelCancel, { exact: true }).click();
      await expect(page.getByText(labelDuplicateDashboard)).toHaveCount(0);
      expect(mocks.create.bodies).toHaveLength(0);
    });

    test('requires a duplicate name of three characters at least', async ({
      page
    }) => {
      await openAction(page, labelDuplicate);
      await expect(nameField(page)).toHaveValue('My Dashboard_1');
      await nameField(page).fill('ab');
      await expect(button(page, labelDuplicate).last()).toBeDisabled();
    });

    test('updates the dashboard properties', async ({ page }) => {
      await openAction(page, labelEditProperties);
      await expect(nameField(page)).toHaveValue('My Dashboard');
      await expect(
        page.getByRole('textbox', { name: labelDescription })
      ).toHaveValue('my description');
      await expect(button(page, labelUpdate)).toBeDisabled();
      await nameField(page).fill('New name');
      await page
        .getByRole('textbox', { name: labelDescription })
        .fill('New description');
      await button(page, labelUpdate).click();
      await expect.poll(() => mocks.update.bodies.length).toBe(1);
      const body = String(mocks.update.bodies[0]);
      expect(body).toContain('New name');
      expect(body).toContain('New description');
      await expect(page.getByText(labelDashboardUpdated)).toBeVisible();
    });

    test('requires an updated name of three characters at least', async ({
      page
    }) => {
      await openAction(page, labelEditProperties);
      await nameField(page).fill('ab');
      await expect(button(page, labelUpdate)).toBeDisabled();
    });

    test('saves the shares added to a dashboard', async ({ page }) => {
      await page.getByTestId(labelShareWithContacts).first().click();
      await page.getByLabel(labelAddAContact).click();
      await page.getByText('User', { exact: true }).locator('..').click();
      await page.getByTestId('add').click();
      await page.getByText(labelSave, { exact: true }).click();
      await expect.poll(() => mocks.shares.bodies.length).toBe(1);
      await expect(page.getByText(labelSharesSaved)).toBeVisible();
    });
  });
}

test.describe('Navigation to dashboard', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUp({ mockApi, mount });
    await page.getByTestId('View as list').click();
    await expect(page.getByText('Arnaud')).toBeVisible();
  });

  test('opens the dashboard of a clicked row', async ({ page }) => {
    await page.getByText('Arnaud').click();
    await expect(location(page)).toHaveValue('/home/dashboards/library/1');
  });

  test('stays on the library when the actions cell is clicked', async ({
    page
  }) => {
    await page
      .locator('.MuiTableRow-root .MuiTableCell-body')
      .last()
      .click({ position: { x: 2, y: 2 } });
    await expect(location(page)).toHaveValue('/');
  });
});

test.describe('Favorite dashboards', () => {
  for (const [position, view] of [labelCardsView, labelListView].entries()) {
    for (const action of ['add', 'remove'] as const) {
      test(`${action === 'add' ? 'adds' : 'removes'} a favorite from the ${view}`, async ({
        mockApi,
        mount,
        page
      }) => {
        const removeFavorite = await mockApi({
          endpoint: `configuration/dashboards/${position + 1}/favorites`,
          method: 'DELETE'
        });
        await setUp(
          { mockApi, mount },
          { listing: action === 'add' ? favoritesList : allFavorites }
        );
        await expectListing(page);
        const updated = {
          add: [listAddedOne, listAddedTwo],
          remove: [listRemovedOne, listRemovedTwo]
        }[action][position];
        await mockApi({ endpoint, response: updated });
        await page.getByRole('button', { name: view }).click();
        const buttons = page.getByRole('button', {
          name: 'FavoriteIconButton'
        });
        const favorite = position === 0 ? buttons.first() : buttons.last();
        const [title, updatedTitle, success] =
          action === 'add'
            ? [
                labelAddToFavorites,
                labelRemoveFromFavorites,
                labelDashboardAddedToFavorites
              ]
            : [
                labelRemoveFromFavorites,
                labelAddToFavorites,
                labelDashboardRemovedFromFavorites
              ];
        await favorite.dispatchEvent('mouseover');
        await expect(page.getByText(title)).toBeVisible();
        await favorite.click();
        const sent = action === 'add' ? mocks.addFavorite : removeFavorite;
        await expect.poll(() => sent.urls.length).toBe(1);
        await expect(page.getByText(success)).toBeVisible();
        await favorite.dispatchEvent('mouseover');
        await expect(page.getByText(updatedTitle)).toBeVisible();
      });
    }
  }

  test('lists the favorites when the favorite filter is checked', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await expectListing(page);
    await page.locator('[type="checkbox"]').check();
    await expect.poll(() => mocks.favorites.urls.length).toBeGreaterThan(0);
    const calls = mocks.listing.urls.length;
    await page.locator('[type="checkbox"]').uncheck();
    await expect.poll(() => mocks.listing.urls.length).toBeGreaterThan(calls);
  });
});
