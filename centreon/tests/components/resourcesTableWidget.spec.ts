import type { Page } from '@playwright/test';

import acknowledgement from '../../cypress/fixtures/Widgets/ResourcesTable/acknowledgement.json';
import downtime from '../../cypress/fixtures/Widgets/ResourcesTable/downtime.json';
import resourcesStatus from '../../cypress/fixtures/Widgets/ResourcesTable/resourcesStatus.json';
import resourcesByHost from '../../cypress/fixtures/Widgets/ResourcesTable/resourecesStatusViewByHost.json';
import {
  labelAcknowledge,
  labelAcknowledgeCommandSent,
  labelAction,
  labelCheck,
  labelCheckCommandSent,
  labelCloseATicket,
  labelCloseTicket,
  labelConfirm,
  labelCreateticket,
  labelDowntimeCommandSent,
  labelEndDateGreaterThanStartDate,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelNotes,
  labelOpenTicketForHost,
  labelOpenTicketForService,
  labelSetDowntime,
  labelSticky,
  labelTicketClosed,
  labelTicketWillBeClosedInTheProvider
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-resourcestable/src/Listing/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/SingleInstancePage/Dashboard/Widgets/
// centreon-widget-resourcestable/src/spec/ResourcesTable.
const storyId = 'centreon-widget-resourcestable/src/ResourcesTable/Default';

interface TableData {
  columnsForViewByHost: Array<string>;
  columnsForViewByService: Array<string>;
  metaServiceResources: Array<unknown>;
  options: Record<string, unknown>;
  resources: Array<unknown>;
  resourcesRegex: Array<unknown>;
  selectedColumnIds: Array<string>;
}

interface Mocks {
  acknowledge: MockedEndpoint;
  byHost: MockedEndpoint;
  check: MockedEndpoint;
  closeTicket: MockedEndpoint;
  downtime: MockedEndpoint;
  publicWidget: MockedEndpoint;
  resources: MockedEndpoint;
}

let data: TableData;
let mocks: Mocks;

const mockApis = async (mockApi: MockApi): Promise<Mocks> => {
  await mockApi({ endpoint: 'acknowledgements', response: acknowledgement });
  await mockApi({ endpoint: 'downtimes', response: downtime });

  return {
    acknowledge: await mockApi({
      endpoint: './api/latest/monitoring/resources/acknowledge',
      method: 'POST',
      response: {}
    }),
    byHost: await mockApi({
      endpoint: './api/latest/monitoring/resources/hosts',
      response: resourcesByHost
    }),
    check: await mockApi({
      endpoint: './api/latest/monitoring/resources/check',
      method: 'POST',
      response: {}
    }),
    closeTicket: await mockApi({
      endpoint: 'rules/closeTicket/action.php',
      method: 'POST',
      response: { code: 0, msg: 'Ticket closed: 12' }
    }),
    downtime: await mockApi({
      endpoint: './api/latest/monitoring/resources/downtime',
      method: 'POST',
      response: {}
    }),
    publicWidget: await mockApi({
      endpoint: 'dashboards/playlists/hash/dashboards/1/widgets/1',
      response: resourcesStatus
    }),
    resources: await mockApi({
      endpoint: './api/latest/monitoring/resources',
      response: resourcesStatus
    })
  };
};

test.use({ viewport: { height: 768, width: 1366 } });

test.beforeEach(async ({ loadStoryData, mockApi }) => {
  data = await loadStoryData<TableData>(
    'centreon-widget-resourcestable/src/ResourcesTable/Data',
    'resourcesTableData'
  );
  mocks = await mockApis(mockApi);
});

type Mount = (id: string, props?: object) => Promise<unknown>;

const render = (
  mount: Mount,
  options: Record<string, unknown> = {},
  { isPublic = false, resources = data.resources } = {}
) =>
  mount(storyId, {
    data: { resources },
    isPublic,
    options: { ...data.options, ...options }
  });

const lastQuery = (mock: MockedEndpoint, name: string): string | null => {
  const url = mock.urls.at(-1);

  return url ? new URL(url).searchParams.get(name) : null;
};

const expectListingRows = async (page: Page): Promise<void> => {
  for (const name of ['Centreon-Server', 'Disk-/', 'Load', 'Memory', 'Ping']) {
    await expect(page.getByText(name).first()).toBeVisible();
  }
};

const selectRows = async (page: Page): Promise<void> => {
  await page.getByLabel('Select row 19').click();
  await page.getByLabel('Select row 24').click();
};

const selectedResources = [
  { id: 19, parent: { id: 14 }, type: 'service' },
  { id: 24, parent: { id: 14 }, type: 'service' }
];

test('requests the public API on a public page', async ({ mount }) => {
  await render(mount, {}, { isPublic: true });
  await expect.poll(() => mocks.publicWidget.urls.length).toBeGreaterThan(0);
  expect(mocks.resources.urls).toHaveLength(0);
});

test.describe('View by all', () => {
  test('retrieves resources', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await render(mount);
    await expectListingRows(page);
    await expectThemedScreenshot(page, 'resources-table-view-all');
  });

  test('searches the regex resources', async ({ mount }) => {
    await render(mount, {}, { resources: data.resourcesRegex });
    await expect
      .poll(() => lastQuery(mocks.resources, 'search'))
      .toBe(
        '{"$and":[{"$or":[{"parent_name":{"$rg":"^H1$"}}]},{"$or":[{"name":{"$rg":"^Loa"}}]}]}'
      );
  });

  for (const [title, options, name, value] of [
    ['the limit', { limit: 30 }, 'limit', '30'],
    [
      'the sort',
      { sortField: 'name', sortOrder: 'desc' },
      'sort_by',
      '{"name":"desc"}'
    ],
    ['the resource types', {}, 'types', '["host","service","metaservice"]'],
    ['the states', { states: ['in_downtime'] }, 'states', '["in_downtime"]'],
    [
      'the statuses',
      {},
      'statuses',
      '["OK","UP","DOWN","CRITICAL","UNREACHABLE","UNKNOWN"]'
    ]
  ] as const) {
    test(`requests the listing with ${title} of the widget properties`, async ({
      mount
    }) => {
      await render(mount, options);
      await expect.poll(() => lastQuery(mocks.resources, name)).toBe(value);
    });
  }

  test('requests the listing with the host groups of the widget data', async ({
    mount
  }) => {
    await render(mount, { limit: 50 });
    await expect
      .poll(() => lastQuery(mocks.resources, 'hostgroup_names'))
      .toBe('["HG1","HG2"]');
  });

  test('displays the limit of the widget properties', async ({
    mount,
    page
  }) => {
    await render(mount, { limit: 30 });
    await expect(page.getByText('30', { exact: true }).first()).toBeAttached();
  });

  test('displays the selected columns', async ({ mount, page }) => {
    await render(mount, { selectedColumnIds: data.selectedColumnIds });
    await expect(page.getByText('Ping').first()).toBeAttached();
  });

  for (const { color, name, state } of [
    { color: 'rgb(223, 210, 185)', name: 'Load', state: 'acknowledged' },
    { color: 'rgb(229, 216, 243)', name: 'Disk-/', state: 'in_downtime' }
  ]) {
    test(`highlights the ${state} rows`, async ({ mount, page }) => {
      await render(mount, { states: [state] });
      await expect(
        page.getByText(name, { exact: true }).first().locator('..')
      ).toHaveCSS('background-color', color);
    });
  }

  test('displays the acknowledgement details when its icon is hovered', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await render(mount, { limit: 100, states: ['acknowledged'] });
    await page.getByLabel('Load Acknowledged').dispatchEvent('mouseover');
    for (const value of [
      'Author',
      'admin',
      'Comment',
      'Acknowledged by admin'
    ]) {
      await expect(page.getByText(value).first()).toBeVisible();
    }
    await expectThemedScreenshot(page, 'resources-table-acknowledgement');
  });

  test('displays the downtime details when its icon is hovered', async ({
    mount,
    page
  }) => {
    await render(mount, { limit: 10, states: ['in_downtime'] });
    await page.getByLabel('Disk-/ In downtime').dispatchEvent('mouseover');
    for (const value of [
      'Author',
      'admin',
      'Comment',
      'Downtime set by admin'
    ]) {
      await expect(page.getByText(value).first()).toBeVisible();
    }
  });

  test('sends a forced check for the selected resources', async ({
    mount,
    page
  }) => {
    await render(mount, { limit: 30 });
    await selectRows(page);
    await page.getByLabel(labelForcedCheck, { exact: true }).click();
    await expect
      .poll(() => mocks.check.bodies)
      .toEqual([{ check: { is_forced: true }, resources: selectedResources }]);
    await expect(page.getByText(labelForcedCheckCommandSent)).toBeVisible();
  });

  test('sends a check for the selected resources', async ({ mount, page }) => {
    await render(mount, { limit: 30 });
    await selectRows(page);
    await page.getByLabel('arrow').click();
    await page.getByText(labelCheck, { exact: true }).click();
    await page.getByLabel('arrow').click();
    await page.getByLabel(labelCheck, { exact: true }).click();
    await expect
      .poll(() => mocks.check.bodies)
      .toEqual([{ check: { is_forced: false }, resources: selectedResources }]);
    await expect(page.getByText(labelCheckCommandSent)).toBeVisible();
  });

  test('sends an acknowledgement for the selected resources', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await render(mount, { limit: 30 });
    await selectRows(page);
    await page.getByLabel(labelAcknowledge, { exact: true }).click();
    await page.getByText(labelSticky).click();
    await expectThemedScreenshot(
      page.getByRole('dialog'),
      'resources-table-acknowledge-form'
    );
    await page.getByLabel(labelAcknowledge, { exact: true }).nth(2).click();
    await expect
      .poll(() => mocks.acknowledge.bodies)
      .toEqual([
        {
          acknowledgement: {
            comment: 'Acknowledged by ',
            is_notify_contacts: true,
            is_persistent_comment: false,
            is_sticky: true,
            with_services: true
          },
          resources: selectedResources
        }
      ]);
    await expect(page.getByText(labelAcknowledgeCommandSent)).toBeVisible();
  });

  test('sends a downtime for the selected resources', async ({
    mount,
    page
  }) => {
    await page.clock.setFixedTime(new Date('2024-08-08T00:00:00Z'));
    await render(mount, { limit: 30 });
    await selectRows(page);
    await page.getByLabel(labelSetDowntime, { exact: true }).click();
    await page.getByLabel(labelSetDowntime, { exact: true }).nth(1).click();
    await expect
      .poll(() => mocks.downtime.bodies)
      .toEqual([
        {
          downtime: {
            comment: 'Downtime set by ',
            duration: 3600,
            end_time: '2024-08-08T01:00:00Z',
            is_fixed: true,
            start_time: '2024-08-08T00:00:00Z',
            with_services: false
          },
          resources: selectedResources
        }
      ]);
    await expect(page.getByText(labelDowntimeCommandSent)).toBeVisible();
  });

  test('refuses a downtime starting after its end', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await page.clock.setFixedTime(new Date('2024-08-08T00:00:00Z'));
    await render(mount, { limit: 30 });
    await selectRows(page);
    await page.getByLabel(labelSetDowntime, { exact: true }).click();
    const dialog = page.getByRole('dialog');
    // An end in March: wait for the default dates, then select the month
    // section only before typing, as in the Resources actions spec.
    const end = dialog.locator('input').nth(1);
    await expect(end).toHaveValue(/2024/);
    await end.click({ position: { x: 5, y: 10 } });
    await end.pressSequentially('03');
    await expect(
      page.getByText(labelEndDateGreaterThanStartDate)
    ).toBeVisible();
    await expect(page.getByTestId('Confirm')).toBeDisabled();
    await expectThemedScreenshot(dialog, 'resources-table-downtime-invalid');
  });

  test('opens the meta service details when its row is clicked', async ({
    mount,
    page
  }) => {
    await render(
      mount,
      { limit: 50 },
      { resources: data.metaServiceResources }
    );
    await expect
      .poll(() => lastQuery(mocks.resources, 'search'))
      .toBe('{"$and":[{"$or":[{"name":{"$rg":"^Meta service$"}}]}]}');
    await page.evaluate(() => {
      const opened: Array<string> = [];
      Object.assign(window, { opened });
      window.open = (url) => {
        opened.push(String(url));

        return null;
      };
    });
    await page.getByText('SA_Total_FW_Connexion').click();
    await expect
      .poll(() =>
        page.evaluate(
          () => (window as unknown as { opened: Array<string> }).opened
        )
      )
      .toEqual([
        '/monitoring/resources?details=%7B%22id%22%3A6%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fmetaservices%2F6%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22m6%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%2C%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%2C%7B%22id%22%3A%22metaservice%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%2C%7B%22id%22%3A%22UNREACHABLE%22%2C%22name%22%3A%22Unreachable%22%7D%2C%7B%22id%22%3A%22UNKNOWN%22%2C%22name%22%3A%22Unknown%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbMeta%20service%5C%5Cb%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
      ]);
  });
});

test.describe('View by service', () => {
  test.beforeEach(async ({ mount }) => {
    await render(mount, { displayType: 'service', limit: 20 });
  });

  test('retrieves resources with the widget limit and columns', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expectListingRows(page);
    await expect(page.getByText('20', { exact: true }).first()).toBeAttached();
    for (const column of data.columnsForViewByService) {
      await expect(page.getByText(column).first()).toBeAttached();
    }
    expect(lastQuery(mocks.resources, 'types')).toBe(
      '["service","metaservice"]'
    );
    await expectThemedScreenshot(page, 'resources-table-view-service');
  });
});

test.describe('View by host', () => {
  test.beforeEach(async ({ mount }) => {
    await render(mount, { displayType: 'host', limit: 30 });
  });

  test('displays the columns of the view by host', async ({ page }) => {
    await expect(page.getByText('Centreon-Server').first()).toBeVisible();
    for (const column of data.columnsForViewByHost) {
      await expect(page.getByText(column).first()).toBeAttached();
    }
  });

  test('displays the services when a host is expanded', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect.poll(() => mocks.byHost.urls.length).toBeGreaterThan(0);
    await page.getByTestId('ExpandMoreIcon').click();
    await expectListingRows(page);
    await expectThemedScreenshot(page, 'resources-table-view-host');
  });
});

test.describe('Open tickets', () => {
  const ticketOptions = (withTicket: boolean) => ({
    displayResources: withTicket ? 'withTicket' : 'withoutTicket',
    enableHostTicketCreation: true,
    enableServiceTicketCreation: true,
    isOpenTicketEnabled: true,
    provider: { id: 1, name: 'Rule 1' },
    selectedColumnIds: [
      ...data.selectedColumnIds,
      ...(withTicket
        ? ['ticket_id', 'ticket_subject', 'ticket_open_time', 'action']
        : ['open_ticket'])
    ]
  });

  test('displays the ticket column for the resources without ticket', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    // Known app bug: OpenTicket reads row.parent.id while rendering (React
    // Compiler memoization) and host rows have no parent. Cypress builds with
    // coverage, which disables the compiler, so it never saw the crash.
    test.fail();
    await render(mount, ticketOptions(false));
    await expect(page.getByText('Ticket', { exact: true })).toBeVisible();
    await expectThemedScreenshot(page, 'resources-table-open-ticket');
  });

  test('displays the ticket details for the resources with a ticket', async ({
    mount,
    page
  }) => {
    await render(mount, ticketOptions(true));
    for (const column of ['Ticket ID', 'Ticket subject', 'Opened on']) {
      await expect(page.getByText(column)).toBeAttached();
    }
    await expect(page.getByText('Action', { exact: true })).toBeVisible();
  });

  test('opens the ticket creation form of a service then of a host', async ({
    mount,
    page
  }) => {
    // Known app bug: OpenTicket reads row.parent.id while rendering (React
    // Compiler memoization) and host rows have no parent. Cypress builds with
    // coverage, which disables the compiler, so it never saw the crash.
    test.fail();
    await render(mount, ticketOptions(false));
    const serviceTicket = page.getByLabel(labelOpenTicketForService).nth(1);
    await expect(serviceTicket).toBeVisible();
    await serviceTicket.click();
    await expect(page.getByText(labelCreateticket)).toBeVisible();
    await expect(page.locator('iframe')).toHaveAttribute(
      'src',
      './main.get.php?p=60421&cmd=3&rule_id=1&host_id=14&service_id=24'
    );
    await page.getByLabel('close').click();
    await page.getByLabel(labelOpenTicketForHost).nth(1).click();
    await expect(page.locator('iframe')).toHaveAttribute(
      'src',
      './main.get.php?p=60421&cmd=4&rule_id=1&host_id=14'
    );
  });

  for (const [resource, index, selection] of [
    ['service', 0, '14;19'],
    ['host', 2, '6']
  ] as const) {
    test(`closes the ticket of a ${resource} once confirmed`, async ({
      mount,
      page
    }) => {
      await render(mount, ticketOptions(true));
      await page.getByLabel(labelCloseTicket).nth(index).click();
      await expect(page.getByText(labelCloseATicket)).toBeVisible();
      await expect(
        page.getByText(labelTicketWillBeClosedInTheProvider.split('{{id}}')[1])
      ).toBeVisible();
      await page.getByText(labelConfirm, { exact: true }).click();
      await expect
        .poll(() => mocks.closeTicket.bodies)
        .toEqual([{ data: { rule_id: '1', selection } }]);
      await expect(page.getByText(labelTicketClosed)).toBeVisible();
    });
  }
});

test.describe('Notes and Action URL columns', () => {
  const withUrls = () => ({
    selectedColumnIds: [...data.selectedColumnIds, 'notes_url', 'action_url']
  });

  test('hides the Notes and Action URL columns by default', async ({
    mount,
    page
  }) => {
    await render(mount, { selectedColumnIds: undefined });
    await expect(page.getByText('Centreon-Server').first()).toBeVisible();
    await expect(page.getByTestId('LinkIcon')).toHaveCount(0);
    await expect(page.getByTestId('FlashOnIcon')).toHaveCount(0);
    await page.getByLabel('Add columns').click();
    await expect(page.getByText(`${labelNotes} (N)`)).toBeVisible();
    await expect(page.getByText(`${labelAction} (A)`)).toBeVisible();
  });

  test('links the note icons and shows the note in their tooltip', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await render(mount, withUrls());
    const restart = page.getByTestId('Restart me');
    await expect(page.locator('a', { has: restart })).toHaveAttribute(
      'href',
      'https://example.com/restart'
    );
    await restart.dispatchEvent('mouseover');
    await expect(page.getByRole('tooltip')).toHaveText('Restart me');
    await expectThemedScreenshot(page, 'resources-table-note-tooltip');

    const manual = page.getByTestId('https://example.com/manual');
    await expect(page.locator('a', { has: manual })).toHaveAttribute(
      'href',
      'https://example.com/manual'
    );
    await manual.dispatchEvent('mouseover');
    await expect(
      page
        .getByRole('tooltip')
        .filter({ hasText: 'https://example.com/manual' })
    ).toBeVisible();
  });

  test('shows a note without URL as a non-clickable icon', async ({
    mount,
    page
  }) => {
    await render(mount, withUrls());
    const note = page.getByTestId('Maintenance info');
    await expect(note).toContainText('N');
    await expect(page.locator('a', { has: note })).toHaveCount(0);
    await note.dispatchEvent('mouseover');
    await expect(
      page.getByRole('tooltip').filter({ hasText: 'Maintenance info' })
    ).toBeVisible();
  });

  test('links the action icons and leaves the other resources empty', async ({
    mount,
    page
  }) => {
    await render(mount, withUrls());
    await expect(
      page.locator('a', { has: page.getByTestId('https://example.com/action') })
    ).toHaveAttribute('href', 'https://example.com/action');
    await expect(page.getByTestId('LinkIcon')).toHaveCount(2);
    await expect(page.getByTestId('FlashOnIcon')).toHaveCount(1);
    await expect(page.getByTestId('Maintenance info')).toBeVisible();
  });
});
