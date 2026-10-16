import type { Page } from '@playwright/test';

import hostCategory from '../../cypress/fixtures/resources/filter/hostCategory.json';
import hostGroups from '../../cypress/fixtures/resources/filter/hostGroups.json';
import hostSeverity from '../../cypress/fixtures/resources/filter/hostSeverity.json';
import pollers from '../../cypress/fixtures/resources/filter/pollers/pollers.json';
import pollersWithSpaceOnName from '../../cypress/fixtures/resources/filter/pollers/pollersWithSpaceOnName.json';
import resourcesByHostType from '../../cypress/fixtures/resources/filter/resourcesByHostType.json';
import resourcesByServiceType from '../../cypress/fixtures/resources/filter/resourcesByServiceType.json';
import webAccessServiceGroup from '../../cypress/fixtures/resources/filter/webAccessServiceGroup.json';
import {
  informationLabel,
  labelShowMoreFilters
} from '../../www/front_src/src/Resources/Filter/criteriasNewInterface/translatedLabels';
import {
  labelAcknowledged,
  labelAll,
  labelAllAlerts,
  labelDown,
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelHostSeverity,
  labelMetaService,
  labelMonitoringServer,
  labelOk,
  labelSearch,
  labelSearchOptions,
  labelService,
  labelServiceGroup,
  labelState,
  labelStateFilter,
  labelStatus,
  labelType,
  labelUnreachable,
  labelUp
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, type MockApi, test } from './fixtures';

// Migrated from Resources/Filter/Filter.
const storyId = 'Resources/Filter/Filter/Default';

type Mount = (id: string, props?: object) => Promise<unknown>;
type View = 'All' | 'By host' | 'By service';

const emptyListData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

const resourcesByHostTypeName = 'SensorProbe-Datacenter-04';
const resourcesByServiceTypeName = 'nbr-connect';
const pollerName = 'Central';
const hostCategoryName = 'Europe';
const hostSeverityName = 'Priority_1';
const linuxServersHostGroupName = 'Linux-servers';
const webAccessServiceGroupName = 'Web-access';

const mountFilter = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  props: { unhandledProblems?: boolean; view?: View } = {}
): Promise<void> => {
  for (const [endpoint, response] of [
    ['./api/latest/monitoring/resources', resourcesByHostType],
    ['monitoring/services/names', resourcesByServiceType],
    ['monitoring/servers', pollers],
    ['hostgroups', hostGroups],
    ['servicegroups', webAccessServiceGroup],
    ['monitoring/hosts/categories', hostCategory],
    ['monitoring/severities/host', hostSeverity]
  ] as const) {
    await mockApi({ endpoint, response: response ?? emptyListData });
  }
  await mount(storyId, props);
};

const searchBar = (page: Page) => page.getByPlaceholder(labelSearch);

// The search terms in either order.
const anyOrder = (value: string): RegExp => {
  const words = value.split(' ');

  return new RegExp(
    `(?:${words.join('.*')}|${[...words].reverse().join('.*')})`
  );
};

const searchValue = (value: string, view: View): RegExp => {
  if (view === 'All') {
    return anyOrder(value);
  }
  if (view === 'By service') {
    return anyOrder(`${value} type:service,metaservice`);
  }

  return anyOrder(`${value} type:host `);
};

interface Criteria {
  criteria: string;
  extended?: boolean;
  search: string;
  testId?: string;
  type: 'checkbox' | 'select';
  value: string;
  views?: Array<View>;
}

// In the view of all resources, picking a host or a service adds its type.
const typedSearch = (criteria: string, value: string, view: View): string => {
  if (view !== 'All') {
    return value;
  }

  return criteria === 'host' ? `${value} type:host` : `${value} type:service`;
};

const criterias = (view: View): Array<Criteria> => [
  {
    criteria: 'host',
    search: typedSearch('host', `parent_name:${resourcesByHostTypeName}`, view),
    type: 'select',
    value: resourcesByHostTypeName
  },
  {
    criteria: 'service',
    search: typedSearch('service', `name:${resourcesByServiceTypeName}`, view),
    type: 'select',
    value: resourcesByServiceTypeName
  },
  {
    criteria: labelState,
    search: 'state:acknowledged',
    testId: 'states',
    type: 'checkbox',
    value: labelAcknowledged
  },
  {
    criteria: labelStatus,
    search: 'status:ok',
    testId: 'statuses-service',
    type: 'checkbox',
    value: labelOk
  },
  {
    criteria: labelStatus,
    search: 'status:up',
    testId: 'statuses-host',
    type: 'checkbox',
    value: labelUp,
    views: ['All', 'By service']
  },
  {
    criteria: labelType,
    search: 'type:metaservice',
    testId: 'resource_types',
    type: 'checkbox',
    value: labelMetaService,
    views: ['All']
  },
  {
    criteria: labelHostGroup,
    search: `host_group:${linuxServersHostGroupName}`,
    type: 'select',
    value: linuxServersHostGroupName
  },
  {
    criteria: labelServiceGroup,
    search: `service_group:${webAccessServiceGroupName}`,
    type: 'select',
    value: webAccessServiceGroupName
  },
  {
    criteria: labelMonitoringServer,
    search: `monitoring_server:${pollerName}`,
    type: 'select',
    value: pollerName
  },
  {
    criteria: labelHostCategory,
    extended: true,
    search: `host_category:${hostCategoryName}`,
    type: 'select',
    value: hostCategoryName
  },
  {
    criteria: labelHostSeverity,
    extended: true,
    search: `host_severity:${hostSeverityName}`,
    type: 'select',
    value: hostSeverityName
  }
];

const checkbox = (page: Page, container: string, id: string) =>
  page.getByTestId(container).locator(`[id="${id}"]`);

test.use({ viewport: { height: 1000, width: 1200 } });

test.describe('Custom filters', () => {
  for (const [filterGroup, search] of [
    [labelAll, ''],
    [labelAllAlerts, 'status:warning,down,critical,unknown ']
  ]) {
    test(`applies the ${filterGroup} filter`, async ({
      expectThemedScreenshot,
      mockApi,
      mount,
      page
    }) => {
      await mountFilter({ mockApi, mount }, { unhandledProblems: true });
      await page.getByLabel(labelStateFilter).click();
      await page.getByText(filterGroup, { exact: true }).click();
      await expect(searchBar(page)).toHaveValue(search);
      await expectThemedScreenshot(
        page,
        `resources-filter-${filterGroup.toLowerCase().replace(' ', '-')}`
      );
    });
  }
});

for (const view of ['All', 'By host', 'By service'] as const) {
  test.describe(`Criterias, view ${view}`, () => {
    test.beforeEach(async ({ mockApi, mount, page }) => {
      await mountFilter({ mockApi, mount }, { view });
      await page.getByLabel(labelSearchOptions).click();
    });

    test('displays the criterias interface', async ({
      expectThemedScreenshot,
      page
    }) => {
      await page.getByText(labelShowMoreFilters).click();
      if (view === 'By host') {
        await expect(checkbox(page, 'resource_types', labelHost)).toBeChecked();
        await expect(searchBar(page)).toHaveValue('type:host ');
        // The host statuses would only filter the hosts the view lists.
        for (const status of [labelUp, labelDown, labelUnreachable]) {
          await expect(page.locator(`[id="${status}"]`)).toHaveCount(0);
        }
      }
      if (view === 'By service') {
        await expect(
          checkbox(page, 'resource_types', labelService)
        ).toBeChecked();
        await expect(
          checkbox(page, 'resource_types', labelMetaService)
        ).toBeChecked();
        await expect(searchBar(page)).toHaveValue('type:service,metaservice ');
      }
      await expectThemedScreenshot(
        page,
        `resources-filter-criterias-${view.toLowerCase().replace(' ', '-')}`
      );
    });

    for (const {
      criteria,
      extended,
      search,
      testId,
      type,
      value,
      views
    } of criterias(view)) {
      if (views && !views.includes(view)) {
        continue;
      }
      test(`syncs the search bar with the ${criteria} criteria set to ${value}`, async ({
        page
      }) => {
        if (extended) {
          await page.getByText(labelShowMoreFilters).click();
        }
        const expected = searchValue(search, view);
        if (type === 'select') {
          await page.getByTestId(criteria).click();
          await page.getByText(value, { exact: true }).click();
          await expect(searchBar(page)).toHaveValue(expected);

          return;
        }
        const box = checkbox(page, testId as string, value);
        await page.getByText(value, { exact: true }).click();
        await expect(box).toBeChecked();
        await expect(searchBar(page)).toHaveValue(expected);
        await page.getByText(value, { exact: true }).click();
        await expect(box).not.toBeChecked();
        await expect(searchBar(page)).not.toHaveValue(expected);
      });
    }

    test('syncs the information field with the search bar', async ({
      page
    }) => {
      await page.getByText(labelShowMoreFilters).click();
      const information = page.getByPlaceholder(informationLabel);
      await information.fill('Information');
      await expect(searchBar(page)).toHaveValue(
        searchValue('information:Information', view)
      );
      await information.clear();
      const cleared = {
        All: ' ',
        'By host': 'type:host  ',
        'By service': 'type:service,metaservice  '
      };
      await expect(searchBar(page)).toHaveValue(cleared[view]);
    });
  });
}

// The backend keeps the spaces in some resource names, such as the pollers.
test.describe('Replaces whitespace with the \\s regex pattern', () => {
  const name = 'Poller test';
  const searched = 'monitoring_server:Poller\\stest';

  test.beforeEach(async ({ mockApi, mount }) => {
    await mountFilter({ mockApi, mount });
    await mockApi({
      endpoint: 'monitoring/servers',
      response: pollersWithSpaceOnName
    });
  });

  test('when the value is picked from the criterias interface', async ({
    page
  }) => {
    await page.getByLabel(labelSearchOptions).click();
    await page.getByTestId(labelMonitoringServer).click();
    await page.getByRole('option', { name }).click();
    await expect(
      page.getByTestId(labelMonitoringServer).locator('..')
    ).toContainText(name);
    await expect(searchBar(page)).toHaveValue(`${searched} `);
  });

  test('when the value is picked from the suggestions', async ({
    expectThemedScreenshot,
    page
  }) => {
    await searchBar(page).pressSequentially('monitoring_server:');
    await page.getByRole('menuitem', { name }).click();
    await expect(searchBar(page)).toHaveValue(searched);
    await expectThemedScreenshot(page, 'resources-filter-poller-with-space');
  });
});

test.describe('Static filters in the search bar', () => {
  test.beforeEach(async ({ mockApi, mount }) => {
    await mountFilter({ mockApi, mount });
  });

  for (const { containerId, id, initial, name, next, value } of [
    {
      containerId: 'status_types',
      id: 'Hard',
      initial: 'HARD',
      name: 'status_type',
      next: ',soft',
      value: 'hard'
    },
    {
      containerId: 'resource_types',
      id: 'Host',
      initial: 'HOST',
      name: 'type',
      next: ',service',
      value: 'host'
    },
    {
      containerId: 'states',
      id: 'Unhandled',
      initial: 'UNHANDLED',
      name: 'state',
      next: ',acknowledged',
      value: 'unhandled'
    },
    {
      containerId: 'statuses-host',
      id: 'Up',
      initial: 'UP',
      name: 'status',
      next: ',down',
      value: 'up'
    }
  ]) {
    test(`ignores the case of the ${name} values`, async ({ page }) => {
      await searchBar(page).pressSequentially(`${name}:${initial}`);
      await searchBar(page).press('Escape');
      await searchBar(page).press('Enter');
      await expect(searchBar(page)).toHaveValue(`${name}:${value} `);
      await page.getByLabel(labelSearchOptions).click();
      await page.getByText(labelShowMoreFilters).click();
      await expect(checkbox(page, containerId, id)).toBeChecked();
    });

    test(`suggests the ${name} values whatever their case`, async ({
      page
    }) => {
      await searchBar(page).pressSequentially(`${name}:${initial[0]}`);
      await expect(page.getByRole('menuitem', { name: value })).toBeVisible();
      await searchBar(page).clear();
      await searchBar(page).pressSequentially(`${name}:${initial}`);
      await expect(page.getByRole('menuitem', { name: next })).toBeAttached();
      await expect(
        page.getByRole('menuitem', { exact: true, name: value })
      ).toHaveCount(0);
    });
  }
});

test.describe('Keyboard actions', () => {
  test.beforeEach(async ({ mockApi, mount }) => {
    await mountFilter({ mockApi, mount });
  });

  const type = async (page: Page, text: string, keys: Array<string> = []) => {
    if (text) {
      await searchBar(page).pressSequentially(text);
    }
    for (const key of keys) {
      await searchBar(page).press(key);
    }
  };

  test('accepts the suggested criteria and value on Enter', async ({
    expectThemedScreenshot,
    page
  }) => {
    await searchBar(page).clear();
    await type(page, 'stat', ['Enter']);
    await expect(searchBar(page)).toHaveValue('state:');
    await type(page, 'u', ['Enter']);
    await expect(searchBar(page)).toHaveValue('state:unhandled');
    await type(page, ' st', ['Enter']);
    await expect(searchBar(page)).toHaveValue('state:unhandled status:');
    await type(page, ' type:', ['ArrowDown', 'Enter']);
    await expect(searchBar(page)).toHaveValue(
      'state:unhandled status: type:service'
    );
    await expectThemedScreenshot(page, 'resources-filter-keyboard');
  });

  test('accepts the suggested dynamic criteria and value on Enter', async ({
    page
  }) => {
    await searchBar(page).clear();
    await type(page, 'host', ['Enter']);
    await expect(searchBar(page)).toHaveValue('host_group:');
    await type(page, 'ESX');
    await expect(page.getByText(linuxServersHostGroupName)).toBeAttached();
    await type(page, '', ['Enter']);
    await expect(searchBar(page)).toHaveValue(
      `host_group:${linuxServersHostGroupName}`
    );
    await type(page, ',');
    await expect(page.getByText('Firewall')).toBeAttached();
    await type(page, '', ['ArrowDown', 'Enter']);
    await expect(searchBar(page)).toHaveValue(
      new RegExp(`^host_group:${linuxServersHostGroupName},\\S+`)
    );
  });
});
