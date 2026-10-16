import type { Page } from '@playwright/test';

import count from '../../cypress/fixtures/resources/listing/count/count.json';
import massiveCount from '../../cypress/fixtures/resources/listing/count/massiveCount.json';
import resourceListing from '../../cypress/fixtures/resources/resourceListing.json';
import { labelCancel } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import {
  labelAcknowledge,
  labelAcknowledgeCommandSent,
  labelAcknowledgeServices,
  labelActionNotPermitted,
  labelAllColumns,
  labelAllPages,
  labelCheck,
  labelCheckCommandSent,
  labelCheckDescription,
  labelComment,
  labelCurrentPageOnly,
  labelDisacknowledge,
  labelDisacknowledgementCommandSent,
  labelDisacknowledgeServices,
  labelDown,
  labelDowntimeCommandSent,
  labelDuration,
  labelEndDateGreaterThanStartDate,
  labelExport,
  labelExportProcessingInProgress,
  labelExportToCSV,
  labelFilteredResources,
  labelFilterRessources,
  labelFixed,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelMoreActions,
  labelNotify,
  labelSelecetPages,
  labelSelectColumns,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelStickyForAnyNonOkStatus,
  labelSubmitStatus,
  labelUnreachable,
  labelUp,
  labelVisibleColumnsOnly,
  labelWarningExportToCsv
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Resources/Actions/Actions.
const storyId = 'Resources/Actions/Actions/Default';
const resources = './api/latest/monitoring/resources';

interface Mocks {
  acknowledge: MockedEndpoint;
  check: MockedEndpoint;
  count: MockedEndpoint;
  disacknowledge: MockedEndpoint;
  downtime: MockedEndpoint;
  submit: MockedEndpoint;
}

const mockActions = async (
  mockApi: MockApi,
  countResponse: unknown = count
): Promise<Mocks> => {
  await mockApi({ endpoint: resources, response: resourceListing });

  return {
    acknowledge: await mockApi({
      endpoint: `${resources}/acknowledge`,
      method: 'POST'
    }),
    check: await mockApi({ endpoint: `${resources}/check`, method: 'POST' }),
    count: await mockApi({
      endpoint: `${resources}/count`,
      response: countResponse
    }),
    disacknowledge: await mockApi({
      endpoint: `${resources}/acknowledgements`,
      method: 'DELETE'
    }),
    downtime: await mockApi({
      endpoint: `${resources}/downtime`,
      method: 'POST'
    }),
    submit: await mockApi({ endpoint: `${resources}/submit`, method: 'POST' })
  };
};

let mocks: Mocks;

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ mockApi, page }) => {
  await page.clock.setFixedTime(new Date('2020-02-01T00:00:00Z'));
  mocks = await mockActions(mockApi);
});

const openMoreActions = async (page: Page) => {
  await page.getByLabel(labelMoreActions, { exact: true }).click();
  // Its hover tooltip would cover the first item of the menu.
  await page.mouse.move(1000, 700);
};
const dialog = (page: Page) => page.getByRole('dialog');
const body = (mock: MockedEndpoint) =>
  mock.bodies[0] as Record<string, Record<string, unknown>>;

test('submits a status for the selected host', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  await mount(storyId, { selected: 'host' });
  await openMoreActions(page);
  await page.getByTestId(labelSubmitStatus).click();
  await dialog(page).getByText(labelUp, { exact: true }).click();
  await expect(page.getByRole('option', { name: labelDown })).toBeVisible();
  await expect(
    page.getByRole('option', { name: labelUnreachable })
  ).toBeVisible();
  await page.getByRole('option', { name: labelDown }).click();
  await dialog(page).getByLabel('Output').fill('output');
  await dialog(page).getByLabel('Performance data').fill('performance data');
  await expectThemedScreenshot(dialog(page), 'resources-submit-status');
  await dialog(page).getByTestId('Confirm').click();

  await expect
    .poll(() => mocks.submit.bodies)
    .toEqual([
      {
        resources: [
          {
            id: 0,
            output: 'output',
            parent: null,
            performance_data: 'performance data',
            status: 1,
            type: 'host'
          }
        ]
      }
    ]);
});

test('disables submit status for an anomaly detection', async ({
  mount,
  page
}) => {
  await mount(storyId, { selected: 'anomalyDetection' });
  await openMoreActions(page);

  // Disabled, but `aria-disabled` reads "[object Object]": `hasADResource` is
  // the resource `find` returns, not a boolean (Resource/index.tsx).
  const submitStatus = page.getByTestId(labelSubmitStatus);
  await expect(submitStatus).toHaveAttribute('aria-disabled');
  await expect(submitStatus).toHaveClass(/Mui-disabled/);
});

test.describe('disacknowledgement', () => {
  const disacknowledge = async (page: Page) => {
    await openMoreActions(page);
    await page.getByTestId('Multiple Disacknowledge').click();
  };
  const confirm = (page: Page) =>
    dialog(page).getByLabel(labelDisacknowledge, { exact: true }).click();

  for (const withServices of [true, false]) {
    test(`disacknowledges a host ${withServices ? 'with' : 'without'} its services`, async ({
      mount,
      page
    }) => {
      await mount(storyId, { selected: 'host' });
      await disacknowledge(page);
      const services = dialog(page).getByLabel(labelDisacknowledgeServices);
      await expect(services).toBeChecked();
      if (!withServices) {
        await services.click();
        await expect(services).not.toBeChecked();
      }
      await confirm(page);

      await expect.poll(() => mocks.disacknowledge.bodies.length).toBe(1);
      expect(body(mocks.disacknowledge).disacknowledgement.with_services).toBe(
        withServices
      );
      await expect(
        page.getByText(labelDisacknowledgementCommandSent)
      ).toBeVisible();
    });
  }

  test('disacknowledges a service', async ({ mount, page }) => {
    await mount(storyId, { selected: 'service' });
    await disacknowledge(page);
    await confirm(page);

    await expect.poll(() => mocks.disacknowledge.bodies.length).toBe(1);
    expect(body(mocks.disacknowledge).disacknowledgement.with_services).toBe(
      true
    );
    expect(body(mocks.disacknowledge).resources).toEqual([
      { id: 0, parent: { id: 1 }, type: 'service' }
    ]);
    await expect(
      page.getByText(labelDisacknowledgementCommandSent)
    ).toBeVisible();
  });

  test('is not permitted without the ACL', async ({ mount, page }) => {
    await mount(storyId, { canPerformActions: false, selected: 'service' });
    await openMoreActions(page);
    const item = page.getByText(labelDisacknowledge, { exact: true });

    await expect(item).toHaveAttribute('aria-disabled', 'true');
    await expect(item.locator('..')).toHaveAttribute(
      'aria-label',
      labelActionNotPermitted
    );
  });
});

test.describe('acknowledgement', () => {
  const confirm = (page: Page) =>
    dialog(page).getByLabel(labelAcknowledge, { exact: true }).click();

  test('acknowledges a host with the default parameters', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(storyId, { selected: 'host' });
    await component.getByText(labelAcknowledge, { exact: true }).click();

    await expect(dialog(page).getByLabel(labelComment)).toHaveValue(
      'Acknowledged by admin'
    );
    await expect(dialog(page).getByLabel(labelNotify)).not.toBeChecked();
    await expect(
      dialog(page).getByLabel(labelStickyForAnyNonOkStatus)
    ).toBeChecked();
    await expect(
      dialog(page).getByLabel(labelAcknowledgeServices)
    ).toBeChecked();
    await expectThemedScreenshot(dialog(page), 'resources-acknowledge');
    await confirm(page);

    await expect.poll(() => mocks.acknowledge.bodies.length).toBe(1);
    expect(body(mocks.acknowledge).acknowledgement).toEqual(
      expect.objectContaining({
        comment: 'Acknowledged by admin',
        is_notify_contacts: false,
        is_persistent_comment: true,
        is_sticky: true,
        with_services: true
      })
    );
    await expect(page.getByText(labelAcknowledgeCommandSent)).toBeVisible();
  });

  test('acknowledges a host with updated parameters', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, { selected: 'host' });
    await component.getByText(labelAcknowledge, { exact: true }).click();
    await dialog(page).getByLabel(labelComment).fill('Acknowledged');
    await dialog(page).getByLabel(labelNotify).check();
    await dialog(page).getByLabel(labelStickyForAnyNonOkStatus).uncheck();
    await dialog(page).getByLabel(labelAcknowledgeServices).uncheck();
    await confirm(page);

    await expect.poll(() => mocks.acknowledge.bodies.length).toBe(1);
    expect(body(mocks.acknowledge).acknowledgement).toEqual(
      expect.objectContaining({
        comment: 'Acknowledged',
        is_notify_contacts: true,
        is_persistent_comment: true,
        is_sticky: false,
        with_services: false
      })
    );
    await expect(page.getByText(labelAcknowledgeCommandSent)).toBeVisible();
  });
});

test.describe('check', () => {
  test('sends a forced check and clears the selection', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, { selected: 'host' });
    await component.getByLabel(labelForcedCheck, { exact: true }).click();

    await expect
      .poll(() => mocks.check.bodies)
      .toEqual([
        {
          check: { is_forced: true },
          resources: [{ id: 0, parent: null, type: 'host' }]
        }
      ]);
    await expect(page.getByText(labelForcedCheckCommandSent)).toBeVisible();
    await expect(page.getByTestId('selected-count')).toHaveValue('0');
  });

  test('sends a check and clears the selection', async ({ mount, page }) => {
    const component = await mount(storyId, { selected: 'host' });
    await component.getByLabel('arrow', { exact: true }).click();
    await page.getByText(labelCheckDescription).click();
    await component.getByLabel(labelCheck, { exact: true }).click();

    await expect
      .poll(() => mocks.check.bodies)
      .toEqual([
        {
          check: { is_forced: false },
          resources: [{ id: 0, parent: null, type: 'host' }]
        }
      ]);
    await expect(page.getByText(labelCheckCommandSent)).toBeVisible();
    await expect(page.getByTestId('selected-count')).toHaveValue('0');
  });
});

test.describe('downtime', () => {
  const openDowntime = async (page: Page) => {
    await page.getByLabel(labelSetDowntime, { exact: true }).click();
    await expect(dialog(page)).toBeVisible();
  };

  test('refuses a start date after the end date', async ({ mount, page }) => {
    await mount(storyId, { selected: 'host' });
    await openDowntime(page);
    const start = dialog(page).locator('input').first();
    // The default dates fill in after the dialog opens: typing earlier turns
    // the start date invalid instead of later than the end.
    await expect(start).toHaveValue(/2020/);
    // A click on its start selects the month section only, as cy.type did; a
    // keyboard focus can select every section, and typing then clears them.
    await start.click({ position: { x: 5, y: 10 } });
    await start.pressSequentially('03');

    await expect(
      dialog(page).getByText(labelEndDateGreaterThanStartDate)
    ).toBeVisible();
    await expect(dialog(page).getByTestId('Confirm')).toBeDisabled();
  });

  test('sets a downtime with the defaults', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, { selected: 'host' });
    await openDowntime(page);
    await expectThemedScreenshot(dialog(page), 'resources-downtime');
    await dialog(page).getByTestId('Confirm').click();

    await expect.poll(() => mocks.downtime.bodies.length).toBe(1);
    expect(body(mocks.downtime).downtime).toEqual(
      expect.objectContaining({
        comment: 'Downtime set by admin',
        duration: 7200,
        is_fixed: true,
        with_services: false
      })
    );
    await expect(page.getByText(labelDowntimeCommandSent)).toBeVisible();
  });

  test('sets a flexible downtime on the services too', async ({
    mount,
    page
  }) => {
    await mount(storyId, { selected: 'host' });
    await openDowntime(page);
    await dialog(page).getByLabel(labelFixed).click();
    await dialog(page)
      .locator(`input[data-testid="${labelDuration}"]`)
      .first()
      .fill('10000');
    await dialog(page).getByLabel(labelSetDowntimeOnServices).check();
    await dialog(page).getByTestId('Confirm').click();

    await expect.poll(() => mocks.downtime.bodies.length).toBe(1);
    expect(body(mocks.downtime).downtime).toEqual(
      expect.objectContaining({
        comment: 'Downtime set by admin',
        duration: 10000,
        is_fixed: false,
        with_services: true
      })
    );
  });
});

test.describe('CSV export', () => {
  const recordWindowOpen = (page: Page) =>
    page.evaluate(() => {
      const opened: Array<Array<unknown>> = [];
      (window as unknown as { opened: typeof opened }).opened = opened;
      window.open = (...args) => {
        opened.push(args);

        return null;
      };
    });
  const opened = (page: Page) =>
    page.evaluate(
      () => (window as unknown as { opened: Array<Array<unknown>> }).opened
    );
  const exportUrl = (allPages: boolean, columns: Array<string>) =>
    `./api/latest/monitoring/resources/export?page=1&limit=10&sort_by=%7B%22status_severity_code%22%3A%22desc%22%2C%22last_status_change%22%3A%22desc%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D&states=%5B%22unhandled_problems%22%5D&statuses=%5B%22WARNING%22%2C%22DOWN%22%2C%22CRITICAL%22%2C%22UNKNOWN%22%5D&all_pages=${allPages}&max_lines=10000${columns
      .map((column) => `&columns[]=${column}`)
      .join('')}&format=csv`;
  const openExport = async (page: Page) => {
    await page.getByRole('button', { name: 'exportCsvButton' }).click();
    await expect(dialog(page)).toBeVisible();
    await expect.poll(() => mocks.count.urls.length).toBeGreaterThan(0);

    return dialog(page);
  };

  test('exports all the columns of all the pages by default', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId);
    await recordWindowOpen(page);
    const modal = await openExport(page);

    for (const text of [
      labelExportToCSV,
      labelFilteredResources,
      labelSelectColumns,
      labelSelecetPages
    ]) {
      await expect(modal.getByText(text).first()).toBeVisible();
    }
    await expect(modal.getByTestId(labelVisibleColumnsOnly)).not.toBeChecked();
    await expect(modal.getByTestId(labelAllColumns)).toBeChecked();
    await expect(modal.getByTestId(labelCurrentPageOnly)).not.toBeChecked();
    await expect(modal.getByTestId(labelAllPages)).toBeChecked();
    await modal.getByTestId(labelCurrentPageOnly).click();
    await modal.getByTestId(labelAllPages).click();
    await expect(modal.getByText(labelWarningExportToCsv)).toBeVisible();
    await expect(
      modal.getByRole('button', { name: labelCancel })
    ).toBeEnabled();
    await expectThemedScreenshot(modal, 'resources-csv-export');
    await modal.getByRole('button', { name: labelExport }).click();

    await expect(modal).toBeHidden();
    await expect(page.getByText(labelExportProcessingInProgress)).toBeVisible();
    expect(await opened(page)).toEqual([
      [
        exportUrl(true, [
          'status',
          'resource',
          'parent_resource',
          'duration',
          'last_check',
          'information',
          'tries',
          'severity',
          'notes_url',
          'action_url',
          'state',
          'alias',
          'parent_alias',
          'fqdn',
          'monitoring_server_name',
          'notification',
          'checks'
        ]),
        'noopener',
        'noreferrer'
      ]
    ]);
  });

  test('exports the visible columns of the current page', async ({
    mount,
    page
  }) => {
    const visibleColumns = [
      'resource',
      'parent_resource',
      'duration',
      'last_check',
      'information',
      'tries'
    ];
    await mount(storyId, { selectedColumnIds: visibleColumns });
    await recordWindowOpen(page);
    const modal = await openExport(page);
    await modal.getByTestId(labelVisibleColumnsOnly).click();
    await expect(modal.getByTestId(labelAllColumns)).not.toBeChecked();
    await modal.getByTestId(labelCurrentPageOnly).click();
    await expect(modal.getByTestId(labelAllPages)).not.toBeChecked();
    await modal.getByRole('button', { name: labelExport }).click();

    await expect(page.getByText(labelExportProcessingInProgress)).toBeVisible();
    expect(await opened(page)).toEqual([
      [exportUrl(false, visibleColumns), 'noopener', 'noreferrer']
    ]);
  });

  test('warns when more than 10,000 resources would be exported', async ({
    mockApi,
    mount,
    page
  }) => {
    mocks = await mockActions(mockApi, massiveCount);
    await mount(storyId);
    const modal = await openExport(page);
    await modal.getByTestId(labelCurrentPageOnly).click();
    await modal.getByTestId(labelAllPages).click();

    for (const text of [
      labelExportToCSV,
      labelWarningExportToCsv,
      labelFilterRessources
    ]) {
      await expect(modal.getByText(text).first()).toBeVisible();
    }
    await expect(
      modal.getByRole('button', { name: labelCancel })
    ).toBeEnabled();
    await expect(
      modal.getByRole('button', { name: labelExport })
    ).toBeEnabled();
  });
});
