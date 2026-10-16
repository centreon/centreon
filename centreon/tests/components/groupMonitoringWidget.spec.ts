import hostGroups from '../../cypress/fixtures/Widgets/GroupMonitoring/hostGroups.json';
import serviceGroups from '../../cypress/fixtures/Widgets/GroupMonitoring/serviceGroups.json';
import {
  labelCritical,
  labelDown,
  labelHosts,
  labelOk,
  labelPending,
  labelServices,
  labelUnknown,
  labelUnreachable,
  labelUp,
  labelWarning
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-groupmonitoring/src/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-groupmonitoring/src/GroupMonitoring.
const storyId = 'centreon-widget-groupmonitoring/src/GroupMonitoring/Default';
const hostGroupsEndpoint = './api/latest/monitoring/hostgroups';

const defaults = { resourceTypes: ['host', 'service'], statuses: ['1', '2'] };
const groupsOf = (type: 'host' | 'service') => ({
  resources: [{ resources: [], resourceType: `${type}-group` }]
});

const groupsLink =
  '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%2C%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%2C%7B%22id%22%3A%22metaservice%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';
const hostStatusesLink =
  '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';

let hostRequests: { urls: Array<URL> };

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: './api/latest/monitoring/servicegroups',
    response: serviceGroups
  });
  hostRequests = await mockApi({
    endpoint: hostGroupsEndpoint,
    response: hostGroups
  });
});

test('reads a public widget from the public API', async ({
  mockApi,
  mount
}) => {
  const publicWidget = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1',
    response: hostGroups
  });

  await mount(storyId, { isPublic: true, panelOptions: defaults });

  await expect.poll(() => publicWidget.urls.length).toBeGreaterThan(0);
});

test('searches the regex groups', async ({ mount }) => {
  await mount(storyId, {
    panelData: {
      resources: [{ resources: '^Loa', resourceType: 'host-group' }]
    },
    panelOptions: defaults
  });

  await expect
    .poll(() => hostRequests.urls[0]?.searchParams.get('search'))
    .toBe('{"$and":[{"$or":[{"name":{"$rg":"^Loa"}}]}]}');
});

const groupTypes = [
  { fixture: hostGroups, title: 'Host groups', type: 'host' as const },
  { fixture: serviceGroups, title: 'Service groups', type: 'service' as const }
];

for (const { fixture, title, type } of groupTypes) {
  test(`lists the ${type} groups with the default statuses`, async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      panelData: groupsOf(type),
      panelOptions: defaults
    });

    await expect(component.getByText(title)).toBeVisible();
    await expect(component.getByText(labelHosts)).toBeVisible();
    await expect(component.getByText(labelServices)).toBeVisible();
    await expect(component.locator('[data-count="0"]')).toHaveCount(63);
    for (const status of [labelDown, labelCritical, labelWarning]) {
      await expect(component.locator(`[data-status="${status}"]`)).toHaveCount(
        21
      );
    }
    for (const { name } of fixture.result) {
      await expect(component.getByText(name, { exact: true })).toBeVisible();
      await expect(component.locator(`[data-group="${name}"]`)).toHaveCount(3);
    }

    if (type === 'host') {
      await expectThemedScreenshot(component, 'group-monitoring');
    }
  });
}

test('displays every status when configured', async ({ mount }) => {
  const component = await mount(storyId, {
    panelOptions: { ...defaults, statuses: ['1', '2', '4', '5', '6'] }
  });
  const status = (label: string) =>
    component.locator(`[data-status="${label}"]`);

  const counts: Array<[string, number, string]> = [
    [labelDown, 21, '0'],
    [labelUnreachable, 21, '0'],
    [labelUp, 21, '1'],
    [labelPending, 42, '0'],
    [labelCritical, 21, '0'],
    [labelWarning, 21, '0'],
    [labelOk, 21, '101'],
    [labelUnknown, 21, '3']
  ];
  for (const [label, count, firstCount] of counts) {
    await expect(status(label)).toHaveCount(count);
    await expect(status(label).first()).toHaveAttribute(
      'data-count',
      firstCount
    );
  }
});

const columns = [
  { hidden: labelServices, resourceTypes: ['host'], visible: labelHosts },
  { hidden: labelHosts, resourceTypes: ['service'], visible: labelServices }
];

for (const { hidden, resourceTypes, visible } of columns) {
  test(`displays only the ${visible} column`, async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: { ...defaults, resourceTypes }
    });

    await expect(component.getByText(visible)).toBeVisible();
    await expect(component.getByText(hidden)).toHaveCount(0);
  });
}

test('sorts the groups by name', async ({ mount }) => {
  const component = await mount(storyId, { panelOptions: defaults });

  await expect
    .poll(() => hostRequests.urls[0]?.href)
    .toContain('sort_by=%7B%22name%22%3A%22ASC%22%7D');
  await component.getByLabel('Column Host groups').click();

  await expect(component.getByTestId('panel-options')).toHaveValue(
    JSON.stringify({ sortField: 'name', sortOrder: 'desc' })
  );
});

test('changes the page', async ({ mount }) => {
  const component = await mount(storyId, { panelOptions: defaults });

  await expect(component.getByLabel('Previous page')).toBeDisabled();
  await expect(component.getByLabel('First page')).toBeDisabled();
  await component.getByLabel('Next page').click();

  await expect(component.getByTestId('panel-options')).toHaveValue(
    JSON.stringify({ limit: 10, page: 1 })
  );
});

test('changes the page size', async ({ mount, page }) => {
  const component = await mount(storyId, { panelOptions: defaults });

  await component.locator('[id="Rows per page"]').click();
  await page.getByRole('option', { exact: true, name: '60' }).click();

  // Changing the page size also goes back to the first page; the Cypress spec
  // only expected the limit.
  await expect(component.getByTestId('panel-options')).toHaveValue(
    JSON.stringify({ limit: 60, page: 0 })
  );
});

test('links the groups to the resources status page', async ({ mount }) => {
  const component = await mount(storyId, { panelOptions: defaults });
  const groupLink = component.getByRole('link', {
    exact: true,
    name: 'Linux-Servers'
  });
  const statusesLink = component
    .locator('[data-group="Linux-Servers"]')
    .first();

  for (const [link, href] of [
    [groupLink, groupsLink],
    [statusesLink, hostStatusesLink]
  ] as const) {
    await expect(link).toHaveAttribute('href', href);
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  }
});

test('does not link the groups in preview', async ({ mount }) => {
  const component = await mount(storyId, {
    isFromPreview: true,
    panelOptions: defaults
  });

  await expect(
    component.getByText('Linux-Servers', { exact: true })
  ).toBeVisible();
  await expect(
    component.getByRole('link', { exact: true, name: 'Linux-Servers' })
  ).toHaveCount(0);
});
