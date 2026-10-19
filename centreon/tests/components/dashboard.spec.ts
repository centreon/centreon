import type { Page } from '@playwright/test';

import contacts from '../../cypress/fixtures/Dashboards/contacts.json';
import complexLayout from '../../cypress/fixtures/Dashboards/Dashboard/complexLayout.json';
import details from '../../cypress/fixtures/Dashboards/Dashboard/details.json';
import detailsWithData from '../../cypress/fixtures/Dashboards/Dashboard/detailsWithData.json';
import detailsWithWebPageWidget from '../../cypress/fixtures/Dashboards/Dashboard/detailsWithWebPageWidget.json';
import expandReduceDetails from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/details.json';
import { federatedWidgets } from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/federatedWidgets';
import graph from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/graph.json';
import groupMonitoring from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/groupmonitoring.json';
import resourcesTable from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/resourcestable.json';
import statusChartHosts from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/statuschartHosts.json';
import statusChartServices from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/statuschartServices.json';
import statusGrid from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/statusgrid.json';
import topBottom from '../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/topbottom.json';
import dashboards from '../../cypress/fixtures/Dashboards/dashboards.json';
import favoriteDetails from '../../cypress/fixtures/Dashboards/favorites/details.json';
import {
  labelAddAWidget,
  labelCancel,
  labelDeleteWidget,
  labelDoYouWantToSaveChanges,
  labelDuplicate,
  labelEditDashboard,
  labelEditWidget,
  labelGlobalRefreshInterval,
  labelIfYouClickOnDiscard,
  labelInterval,
  labelManualRefreshOnly,
  labelMoreActions,
  labelPleaseContactYourAdministrator,
  labelSave,
  labelTitle,
  labelViewProperties,
  labelWidgetType,
  labelYourDashboardHasBeenSaved,
  labelYourRightsOnlyAllowToView
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { labelViewByHost } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-resourcestable/src/Listing/translatedLabels';
import {
  labelAddAContact,
  labelAddToFavorites,
  labelDashboardAddedToFavorites,
  labelDashboardRemovedFromFavorites,
  labelDashboardUpdated,
  labelDelete,
  labelExpand,
  labelReduce,
  labelRemoveFromFavorites,
  labelSharesSaved,
  labelUpdate
} from '../../www/front_src/src/Dashboards/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/SingleInstancePage/Dashboard/Dashboard.
const storyId = 'SingleInstancePage/Dashboard/Dashboard/Default';
const endpoint = './api/latest/configuration/dashboards';

type Mount = (id: string, props?: object) => Promise<unknown>;

interface StoryProps {
  globalRole?: 'administrator' | 'creator' | 'viewer';
  isBlocked?: boolean;
  widgets?: 'default' | 'expandReduce';
}

interface SetUpOptions {
  details?: object;
  ownRole?: 'editor' | 'viewer';
  props?: StoryProps;
}

const roles = {
  editor: { globalRole: 'creator', ownRole: 'editor' },
  viewer: { globalRole: 'viewer', ownRole: 'viewer' },
  viewerAdministrator: { globalRole: 'administrator', ownRole: 'viewer' },
  viewerCreator: { globalRole: 'creator', ownRole: 'viewer' }
} as const;

interface Mocks {
  addFavorite: MockedEndpoint;
  details: MockedEndpoint;
  shares: MockedEndpoint;
  update: MockedEndpoint;
}

let mocks: Mocks;

const mockDetails = (mockApi: MockApi, response: object, ownRole = 'editor') =>
  mockApi({
    endpoint: `${endpoint}/1`,
    response: { ...response, own_role: ownRole }
  });

const setUp = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  {
    details: response = details,
    ownRole = 'editor',
    props = {}
  }: SetUpOptions = {}
): Promise<void> => {
  await mockApi({ endpoint, response: dashboards });
  await mockApi({
    endpoint: 'configuration/dashboards/contacts',
    response: contacts
  });
  mocks = {
    addFavorite: await mockApi({
      endpoint: 'configuration/dashboards/favorites',
      method: 'POST'
    }),
    details: await mockDetails(mockApi, response, ownRole),
    shares: await mockApi({
      endpoint: 'configuration/dashboards/1/shares',
      method: 'PUT',
      status: 204
    }),
    update: await mockApi({
      endpoint: `${endpoint}/1`,
      method: 'POST',
      response: { id: 1 },
      status: 201
    })
  };
  await mount(storyId, props);
  await expect.poll(() => mocks.details.urls.length).toBeGreaterThan(0);
};

const setUpAs = (
  fixtures: { mockApi: MockApi; mount: Mount },
  role: keyof typeof roles,
  options: Omit<SetUpOptions, 'ownRole'> = {}
) =>
  setUp(fixtures, {
    ...options,
    ownRole: roles[role].ownRole,
    props: { ...options.props, globalRole: roles[role].globalRole }
  });

test.use({ viewport: { height: 800, width: 1280 } });

// Widgets display their last refresh time.
test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2024-01-01T10:00:00Z'));
});

const moreActions = (page: Page) =>
  page.getByLabel(labelMoreActions, { exact: true });
const button = (page: Page, label: string) =>
  page.getByLabel(label, { exact: true });
const dashboardState = async (
  page: Page
): Promise<{
  isEditing: boolean;
  layout: Array<{ name: string; options?: Record<string, unknown> }>;
}> => JSON.parse(await page.getByTestId('dashboard-state').inputValue());

// The dashboard form body, URL-encoded or multipart.
const formFields = (body: unknown): Record<string, string> => {
  const text = String(body);
  if (!text.includes('Content-Disposition')) {
    return Object.fromEntries(new URLSearchParams(text));
  }

  return Object.fromEntries(
    [...text.matchAll(/name="([^"]+)"\r?\n\r?\n([^\r\n]*)/g)].map(
      ([, key, value]) => [key, value]
    )
  );
};

const editDashboard = async (page: Page): Promise<void> => {
  await button(page, labelEditDashboard).click();
};

const deleteWidget = async (page: Page, index = 0): Promise<void> => {
  await moreActions(page).nth(index).click();
  await page.getByText(labelDeleteWidget).click();
  await button(page, labelDelete).click();
};

test.describe('Roles', () => {
  test('gives the edition features to an editor', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await expect(page.getByText(labelEditDashboard)).toBeVisible();
    await expect(page.getByText('Widget text').first()).toBeVisible();
    await expect(page.getByText('Generic text').first()).toBeVisible();
    await expect(page.getByText('Description').first()).toBeVisible();
  });

  for (const role of ['viewer', 'viewerCreator'] as const) {
    test(`hides the edition features from a ${role}`, async ({
      mockApi,
      mount,
      page
    }) => {
      await setUpAs({ mockApi, mount }, role);
      await expect(page.getByText('Widget text').first()).toBeVisible();
      await expect(page.getByText(labelEditDashboard)).toHaveCount(0);
    });
  }

  test('gives the edition features to a global administrator viewer', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'viewerAdministrator');
    await expect(page.getByText(labelEditDashboard)).toBeVisible();
  });
});

// What each widget of the expand/reduce dashboard displays.
const widgetContents: Record<string, Array<string>> = {
  'centreon-widget-graph': [graph.metrics[0].metric],
  'centreon-widget-groupmonitoring': [groupMonitoring.result[0].name],
  'centreon-widget-resourcestable': [resourcesTable.result[0].name],
  'centreon-widget-statuschart': [
    String(statusChartServices.total),
    String(statusChartHosts.total)
  ],
  'centreon-widget-statusgrid': [statusGrid.result[0].name],
  'centreon-widget-topbottom': [
    `${topBottom.resources[0].parent_name}_${topBottom.resources[0].name}`
  ]
};

const setUpExpandReduce = async ({
  mockApi,
  mount
}: {
  mockApi: MockApi;
  mount: Mount;
}): Promise<void> => {
  for (const [path, response] of [
    ['monitoring/dashboard/metrics/performances/data', graph],
    ['monitoring/dashboard/metrics/top', topBottom],
    ['monitoring/services/status', statusChartServices],
    ['monitoring/hosts/status', statusChartHosts],
    ['monitoring/hostgroups', groupMonitoring]
  ] as const) {
    await mockApi({ endpoint: `./api/latest/${path}`, response });
  }
  await mockApi({
    endpoint: './api/latest/monitoring/resources/hosts',
    response: { meta: { limit: 10, page: 1, total: 0 }, result: [] }
  });
  // Both widgets list resources: the status grid asks for its tiles only.
  await mockApi({
    endpoint: './api/latest/monitoring/resources',
    response: (url: URL) =>
      url.searchParams.has('sort_by') ? resourcesTable : statusGrid
  });
  await setUp(
    { mockApi, mount },
    { details: expandReduceDetails, props: { widgets: 'expandReduce' } }
  );
};

test.describe('Expand-Reduce', () => {
  test.use({ viewport: { height: 590, width: 1280 } });

  for (const { moduleName } of federatedWidgets) {
    test(`expands and reduces the ${moduleName} widget`, async ({
      mockApi,
      mount,
      page
    }) => {
      await setUpExpandReduce({ mockApi, mount });
      const panel = page.locator('.react-grid-item', {
        has: page.getByLabel(moduleName, { exact: true })
      });
      await panel.scrollIntoViewIfNeeded();
      const contents = widgetContents[moduleName] ?? [];
      for (const content of contents) {
        await expect(panel.getByText(content).first()).toBeVisible();
      }
      await panel.getByLabel(labelMoreActions, { exact: true }).click();
      await page.getByRole('menuitem', { name: labelExpand }).click();
      const dialog = page.getByRole('dialog');
      await expect(dialog).toBeVisible();
      for (const content of contents) {
        await expect(dialog.getByText(content).first()).toBeVisible();
      }
      await dialog.getByLabel(labelReduce).click();
      await expect(dialog).toHaveCount(0);
      for (const content of contents) {
        await expect(panel.getByText(content).first()).toBeVisible();
      }
    });
  }
});

test.describe('Widget interactions in view mode', () => {
  test.use({ viewport: { height: 590, width: 1280 } });

  const resourcesTableOptions = async (page: Page) =>
    (await dashboardState(page)).layout.find(
      ({ name }) => name === 'centreon-widget-resourcestable'
    )?.options;

  test('keeps the dashboard in view mode when a widget selector is used', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUpExpandReduce({ mockApi, mount });
    await page.getByLabel(labelViewByHost).click();
    await expect(page.getByText(labelEditDashboard)).toBeVisible();
    await expect(button(page, labelSave)).toHaveCount(0);
    await expect(page.getByTestId('location')).not.toHaveValue(/edit=true/);
    await expect
      .poll(async () => (await resourcesTableOptions(page))?.displayType)
      .toBe('host');
    expect((await dashboardState(page)).isEditing).toBe(false);
    await page.mouse.move(1200, 550);
    await expectThemedScreenshot(page, 'dashboard-view-mode-selector');
  });

  test('discards the view mode interactions when the edition starts', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpExpandReduce({ mockApi, mount });
    await page.getByLabel(labelViewByHost).click();
    await expect
      .poll(async () => (await resourcesTableOptions(page))?.displayType)
      .toBe('host');
    await editDashboard(page);
    await expect
      .poll(async () => (await dashboardState(page)).isEditing)
      .toBe(true);
    expect((await resourcesTableOptions(page))?.displayType).toBeUndefined();
    await button(page, labelCancel).click();
    await expect(page.getByText(labelEditDashboard)).toBeVisible();
  });
});

const addGenericInput = async (page: Page): Promise<void> => {
  await button(page, labelAddAWidget).click();
  await button(page, labelWidgetType).click();
  await page.getByText('Generic input (example)').click();
  await button(page, labelTitle).fill('Generic input');
  await page
    .getByLabel('Generic text', { exact: true })
    .nth(1)
    .fill('Text for the new widget');
  await button(page, labelSave).nth(1).click();
};

test.describe('Widgets edition', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await editDashboard(page);
  });

  test('adds a widget with its default size', async ({ page }) => {
    await addGenericInput(page);
    await expect(page.getByText('Text for the new widget')).toBeVisible();
    const widget = page.locator('.react-grid-item').nth(3);
    await expect(widget).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 12, 240)');
    await expect(widget).toHaveCSS('width', '598px');
    await expect(widget).toHaveCSS('height', '444px');
  });

  test('edits a widget title and description', async ({
    expectThemedScreenshot,
    page
  }) => {
    await moreActions(page).first().click();
    await page.getByText(labelEditWidget).click();
    await button(page, labelTitle).press('End');
    await button(page, labelTitle).pressSequentially(' updated');
    await page
      .locator('[data-testid="RichTextEditor"]')
      .nth(1)
      .pressSequentially('Description');
    await expect(page.getByTestId('location')).toHaveValue(/edit=true/);
    await button(page, labelSave).nth(1).click();
    await expect(page.getByText(labelEditWidget)).toHaveCount(0);
    await expect(page.getByText('Widget text updated')).toBeVisible();
    const { layout } = await dashboardState(page);
    expect(layout).toHaveLength(3);
    expect(layout[0].name).toBe('centreon-widget-text');
    expect(
      (layout[0].options?.description as { content?: string })?.content
    ).toBe(
      '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Description","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1,"textFormat":0,"textStyle":""}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}'
    );
    await expectThemedScreenshot(page, 'dashboard-edited-widget');
  });

  test('shrinks a widget when its resize handle is dragged', async ({
    page
  }) => {
    const panel = page.locator('[data-can-move="true"]').first().locator('..');
    await expect(panel).toHaveCSS('height', '216px');
    const handle = page.locator('[class*="react-resizable-handle-se"]').first();
    const box = await handle.boundingBox();
    if (!box) {
      throw new Error('No resize handle');
    }
    await page.mouse.move(box.x + 2, box.y + 2);
    await page.mouse.down();
    await page.mouse.move(box.x - 70, box.y - 70, { steps: 5 });
    await page.mouse.move(box.x - 140, box.y - 140, { steps: 5 });
    await page.mouse.up();
    await expect
      .poll(() => panel.evaluate((element) => element.clientHeight))
      .toBeLessThan(216);
  });

  test('duplicates a widget', async ({ page }) => {
    await moreActions(page).first().click();
    await button(page, labelDuplicate).click();
    await expect(page.getByText('Widget text', { exact: true })).toHaveCount(2);
  });

  test('deletes a widget once confirmed', async ({
    expectThemedScreenshot,
    page
  }) => {
    await moreActions(page).first().click();
    await page.getByText(labelDeleteWidget).click();
    await expect(
      page.getByText('The Widget text widget will be permanently deleted.')
    ).toBeVisible();
    await button(page, labelDelete).click();
    await expect(page.getByText('Widget text', { exact: true })).toHaveCount(0);
    await expect(page.getByText(labelAddAWidget)).toBeVisible();
    await expectThemedScreenshot(page, 'dashboard-deleted-widget');
  });

  test('omits the name of an untitled widget in the deletion message', async ({
    page
  }) => {
    await moreActions(page).nth(2).click();
    await page.getByText(labelDeleteWidget).click();
    const message = page.getByText('The widget will be permanently deleted.');
    await expect(message).toBeVisible();
    await page.getByTestId('cancel').click();
    await expect(message).toHaveCount(0);
  });

  test('restores the deleted widgets when the edition is cancelled', async ({
    page
  }) => {
    await deleteWidget(page);
    await expect(page.getByText('Widget text', { exact: true })).toHaveCount(0);
    await page.getByTestId('cancel_dashboard').click();
    await expect(page.getByText('Widget text').first()).toBeVisible();
    await expect(page.getByText('Generic text').first()).toBeVisible();
  });
});

test.describe('Dashboard global properties', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await page.getByLabel('edit', { exact: true }).click();
    await expect(page.getByText(labelGlobalRefreshInterval)).toBeVisible();
    await expect(page.getByText(labelManualRefreshOnly)).toBeVisible();
  });

  test('displays the global properties form', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect(button(page, labelInterval)).toHaveValue('15');
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'dashboard-global-properties'
    );
  });

  test('updates the refresh type and interval', async ({ page }) => {
    await button(page, labelInterval).press('End');
    await button(page, labelInterval).pressSequentially('15');
    await page.getByText(labelManualRefreshOnly).click();
    await button(page, labelUpdate).click();
    await expect.poll(() => mocks.update.bodies.length).toBe(1);
    expect(formFields(mocks.update.bodies[0])).toEqual({
      description: 'my description',
      name: 'My Dashboard',
      'refresh[interval]': '1515',
      'refresh[type]': 'manual'
    });
    await expect(page.getByText(labelDashboardUpdated)).toBeVisible();
  });
});

test('saves the shares added to the dashboard', async ({
  mockApi,
  mount,
  page
}) => {
  await setUpAs({ mockApi, mount }, 'editor');
  await page.getByTestId('share').first().click();
  await page.getByLabel(labelAddAContact).click();
  await page.getByText('User', { exact: true }).locator('..').click();
  await page.getByTestId('add').click();
  await page.getByText(labelSave, { exact: true }).click();
  await expect.poll(() => mocks.shares.bodies.length).toBe(1);
  await expect(page.getByText(labelSharesSaved)).toBeVisible();
});

test('saves an empty dashboard once every widget is deleted', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await setUpAs({ mockApi, mount }, 'editor');
  for (let widget = 0; widget < 3; widget += 1) {
    await deleteWidget(page);
  }
  await expect(moreActions(page)).toHaveCount(0);
  await button(page, labelSave).click();
  await expect.poll(() => mocks.update.bodies.length).toBe(1);
  expect(formFields(mocks.update.bodies[0])).toMatchObject({
    'panels[]': '',
    'thumbnail[directory]': 'dashboards',
    'thumbnail[name]': 'dashboard-1.png'
  });
  await expect(page.getByText(labelYourDashboardHasBeenSaved)).toBeVisible();
  await expectThemedScreenshot(page, 'dashboard-empty-saved');
});

test.describe('Route blocking', () => {
  const duplicateThenLeave = async (page: Page): Promise<void> => {
    await moreActions(page).first().click();
    await button(page, labelDuplicate).click();
    await page.getByTestId('block-redirection').dispatchEvent('click');
  };
  const blockerCalls = (page: Page) =>
    page.evaluate(
      () => (window as unknown as { blockerCalls: Array<string> }).blockerCalls
    );

  test('saves the changes before leaving when confirmed', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await editDashboard(page);
    await duplicateThenLeave(page);
    await expect(page.getByText(labelDoYouWantToSaveChanges)).toBeVisible();
    await expect(page.getByText(labelIfYouClickOnDiscard)).toBeVisible();
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'dashboard-unsaved-changes'
    );
    await page.getByTestId('confirm').click();
    await expect.poll(() => mocks.update.bodies.length).toBe(1);
    expect(formFields(mocks.update.bodies[0])).toMatchObject({
      'thumbnail[directory]': 'dashboards',
      'thumbnail[name]': 'dashboard-1.png'
    });
  });

  test('leaves without saving when discarded', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await duplicateThenLeave(page);
    const before = (await blockerCalls(page)).length;
    await page.getByTestId('cancel').click();
    await expect
      .poll(async () => (await blockerCalls(page)).slice(before))
      .toEqual(['proceed']);
    expect(mocks.update.bodies).toHaveLength(0);
  });

  test('stays on the dashboard when the dialog is closed', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await duplicateThenLeave(page);
    const before = (await blockerCalls(page)).length;
    await page.getByLabel('close', { exact: true }).click();
    await expect
      .poll(async () => (await blockerCalls(page)).slice(before))
      .toEqual(['reset']);
  });
});

test.describe('Dataset links', () => {
  test.beforeEach(async ({ mockApi, mount }) => {
    await setUpAs({ mockApi, mount }, 'editor', { details: detailsWithData });
  });

  const resourcesStatusLink = (page: Page) =>
    page.getByTestId('See more on the Resources Status page');

  test('links a widget to the Resources Status of its resources', async ({
    page
  }) => {
    await expect(resourcesStatusLink(page).first()).toHaveAttribute(
      'href',
      '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbMy%20host%5C%5Cb%22%2C%22name%22%3A%22My%20host%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
    );
  });

  test('links a single metric widget to the graph of its service', async ({
    page
  }) => {
    await expect(resourcesStatusLink(page).nth(2)).toHaveAttribute(
      'href',
      '/monitoring/resources?details=%7B%22id%22%3Anull%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2Fundefined%2Fservices%2Fundefined%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22graph%22%2C%22tabParameters%22%3A%7B%7D%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22h.name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbCentreon-Server%5C%5Cb%22%2C%22name%22%3A%22Centreon-Server%22%7D%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbPing%5C%5Cb%22%2C%22name%22%3A%22Ping%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
    );
  });

  test('links a business activity widget to its page', async ({ page }) => {
    await expect(
      page.getByTestId('See more on the Business Activity page').first()
    ).toHaveAttribute('href', '/monitoring/bam/bas/1');
  });
});

test('renders the web page widgets with their source and refresh button', async ({
  mockApi,
  mount,
  page
}) => {
  await setUp({ mockApi, mount }, { details: detailsWithWebPageWidget });
  const frames = page.getByTestId('Webpage Display');
  await expect(frames).toHaveCount(2);
  await expect(frames.nth(0)).toHaveAttribute(
    'src',
    'https://docs.centreon.com/fr/'
  );
  await expect(frames.nth(1)).toHaveAttribute('src', 'https://react.dev/');
  await expect(page.getByTestId('UpdateIcon')).toHaveCount(2);
});

test.describe('Favorite dashboard', () => {
  const favoriteButton = (page: Page) =>
    page.getByRole('button', { name: 'FavoriteIconButton' });

  for (const action of ['add', 'remove'] as const) {
    test(`${action === 'add' ? 'adds' : 'removes'} the dashboard ${action === 'add' ? 'to' : 'from'} the favorites`, async ({
      mockApi,
      mount,
      page
    }) => {
      const removeFavorite = await mockApi({
        endpoint: 'configuration/dashboards/1/favorites',
        method: 'DELETE'
      });
      await setUp(
        { mockApi, mount },
        { details: action === 'add' ? details : favoriteDetails }
      );
      const [title, updatedTitle, success, updatedDetails] =
        action === 'add'
          ? [
              labelAddToFavorites,
              labelRemoveFromFavorites,
              labelDashboardAddedToFavorites,
              favoriteDetails
            ]
          : [
              labelRemoveFromFavorites,
              labelAddToFavorites,
              labelDashboardRemovedFromFavorites,
              details
            ];
      await mockDetails(mockApi, updatedDetails);
      await favoriteButton(page).dispatchEvent('mouseover');
      await expect(page.getByText(title)).toBeVisible();
      await favoriteButton(page).click();
      const sent = action === 'add' ? mocks.addFavorite : removeFavorite;
      await expect.poll(() => sent.urls.length).toBe(1);
      await expect(page.getByText(success)).toBeVisible();
      await favoriteButton(page).dispatchEvent('mouseover');
      await expect(page.getByText(updatedTitle)).toBeVisible();
    });
  }

  test('disables the favorite button while its request runs', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUp({ mockApi, mount });
    await mockApi({
      delay: 500,
      endpoint: 'configuration/dashboards/favorites',
      method: 'POST'
    });
    await mockDetails(mockApi, favoriteDetails);
    await favoriteButton(page).dblclick();
    await expect(favoriteButton(page)).toBeDisabled();
    await expect(favoriteButton(page)).toBeEnabled();
    await expect(page.getByText(labelDashboardAddedToFavorites)).toBeVisible();
    await favoriteButton(page).dispatchEvent('mouseover');
    await expect(page.getByText(labelRemoveFromFavorites)).toBeVisible();
  });
});

test('places a new widget at the end of a complex layout', async ({
  mockApi,
  mount,
  page
}) => {
  await mockApi({ endpoint: `${endpoint}/1`, response: complexLayout });
  await mount(storyId, {});
  // Cypress relied on the edit=true URL left by a previous test.
  await editDashboard(page);
  await button(page, labelAddAWidget).click();
  await button(page, labelWidgetType).click();
  await page.getByText('Allows you to add free text').click();
  await button(page, labelSave).nth(1).click();
  await expect(page.locator('.react-grid-item').nth(3)).toHaveCSS(
    'transform',
    'matrix(1, 0, 0, 1, 12, 240)'
  );
});

test.describe('Widget form outside the edition', () => {
  test('opens the widget form in edition for an editor', async ({
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'editor');
    await moreActions(page).first().click();
    await button(page, labelEditWidget).click();
    await expect(button(page, labelWidgetType)).toBeEnabled();
    await page.getByLabel('close', { exact: true }).click();
    await expect(button(page, labelWidgetType)).toBeAttached();
  });

  test('opens the widget form read-only for a viewer', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await setUpAs({ mockApi, mount }, 'viewer');
    await moreActions(page).first().click();
    await button(page, labelViewProperties).click();
    await expect(button(page, labelWidgetType)).toBeDisabled();
    await expect(button(page, labelCancel)).toHaveCount(0);
    await expect(button(page, labelSave)).toHaveCount(0);
    await expect(page.getByText(labelYourRightsOnlyAllowToView)).toBeVisible();
    await expect(
      page.getByText(labelPleaseContactYourAdministrator)
    ).toBeVisible();
    await expectThemedScreenshot(page, 'dashboard-widget-view-properties');
  });
});
