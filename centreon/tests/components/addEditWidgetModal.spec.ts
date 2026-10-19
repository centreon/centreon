import type { Page } from '@playwright/test';

import serviceMetric from '../../cypress/fixtures/Dashboards/Dashboard/serviceMetric.json';
import serviceMetrics from '../../cypress/fixtures/Dashboards/Dashboard/serviceMetrics.json';
import {
  labelAddFilter,
  labelAddMetric,
  labelCancel,
  labelDelete,
  labelEditWidget,
  labelGenericWidgets,
  labelMetrics,
  labelPleaseChooseAWidgetToActivatePreview,
  labelRealTimeWidgets,
  labelResourceType,
  labelSave,
  labelSelectAResource,
  labelSelectAWidgetType,
  labelSelectMetric,
  labelShowDescription,
  labelTitle,
  labelWidgetType
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import widgetDataProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-data/properties.json';
import widgetGenericTextProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-generictext/properties.json';
import widgetGraphProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-graph/properties.json';
import widgetInputProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-input/properties.json';
import widgetSingleDataProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-singledata/properties.json';
import widgetSingleMetricProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-singlemetric/properties.json';
import widgetStatusGridProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-statusgrid/properties.json';
import widgetTextProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-text/properties.json';
import widgetTopBottomProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-topbottom/properties.json';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/SingleInstancePage/Dashboard/AddEditWidget/
// AddEditWidgetModal.
const storyId = 'AddEditWidget/AddEditWidgetModal/Default';

type Mount = (id: string, props?: object) => Promise<unknown>;

interface StoryProps {
  initialForm?: 'add' | 'data' | 'edit';
  isEditing?: boolean;
  widgets?: 'categories' | 'default' | 'none' | 'unknownProperty';
}

const generateResources = (label: string) => ({
  meta: { limit: 10, page: 1, total: 10 },
  result: Array.from({ length: 10 }, (_, index) => ({
    id: index,
    name: `${label} ${index}`
  }))
});

const metricsEndpoint = 'monitoring/dashboard/metrics/performances';

let metrics: MockedEndpoint;

const mockData = async (
  mockApi: MockApi,
  metricsResponse: unknown = serviceMetrics
): Promise<void> => {
  await mockApi({
    endpoint: './api/latest/monitoring/resources',
    response: (url: URL) =>
      generateResources(
        url.searchParams.get('types') === '["metaservice"]'
          ? 'Meta service'
          : 'Host'
      )
  });
  metrics = await mockApi({
    endpoint: metricsEndpoint,
    response: metricsResponse
  });
};

test.use({ viewport: { height: 800, width: 1280 } });

const open = (mount: Mount, props: StoryProps = {}) => mount(storyId, props);

const widgetType = (page: Page) => page.getByTestId(labelWidgetType);
const saveButton = (page: Page) => page.getByLabel(labelSave, { exact: true });
const titleField = (page: Page) => page.getByLabel(labelTitle, { exact: true });
const editor = (page: Page, index: number) =>
  page.getByLabel('RichTextEditor').nth(index);

const pickWidget = async (page: Page, title: string): Promise<void> => {
  await widgetType(page).click();
  await page.getByText(title, { exact: true }).click();
};

test.describe('Add widget', () => {
  test('displays the modal', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await open(mount);
    await expect(page.getByText(labelSelectAWidgetType)).toBeVisible();
    await expect(
      page.getByText(labelPleaseChooseAWidgetToActivatePreview)
    ).toBeVisible();
    await expect(page.getByLabel(labelWidgetType).first()).toBeVisible();
    await expect(page.getByLabel(labelCancel, { exact: true })).toBeVisible();
    await expect(saveButton(page)).toBeVisible();
    await expectThemedScreenshot(page, 'add-edit-widget-modal-empty');
  });

  test('enables the save button once the widget properties are filled', async ({
    mount,
    page
  }) => {
    await open(mount);
    await pickWidget(page, 'Generic input (example)');
    await expect(saveButton(page)).toBeDisabled();
    await titleField(page).fill('Generic input');
    await page.getByLabel('Generic text', { exact: true }).fill('Text');
    await expect(page.getByLabel(labelShowDescription)).toBeChecked();
    await expect(saveButton(page)).toBeEnabled();
  });

  test('keeps the title when the widget type changes', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await open(mount);
    await pickWidget(page, 'Generic input (example)');
    await titleField(page).fill('Widget name');
    await page.getByLabel('Generic text', { exact: true }).fill('Text');
    await expect(saveButton(page)).toBeEnabled();
    await pickWidget(page, 'Generic text (example)');
    await expect(titleField(page)).toHaveValue('Widget name');
    await expect(saveButton(page)).toBeEnabled();
    await expect(page.getByText('Hello world').first()).toBeVisible();
    await expectThemedScreenshot(page, 'add-edit-widget-modal-text-widget');
  });

  test('keeps the description editable when it is hidden', async ({
    mount,
    page
  }) => {
    await open(mount);
    await pickWidget(page, 'Generic input (example)');
    await expect(editor(page, 0)).toHaveAttribute('contenteditable', 'true');
    await page.getByLabel(labelShowDescription).uncheck();
    await expect(editor(page, 0)).toHaveAttribute('contenteditable', 'true');
  });

  test('previews the edited title and description', async ({ mount, page }) => {
    await open(mount);
    await pickWidget(page, 'Generic input (example)');
    await titleField(page).fill('Title');
    await editor(page, 0).pressSequentially('Hello');
    await expect(
      page.getByText('Title', { exact: true }).first()
    ).toBeVisible();
    await expect(
      page.getByText('Hello', { exact: true }).first()
    ).toBeVisible();
  });

  test('groups the widgets under their category', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await open(mount, { widgets: 'categories' });
    await widgetType(page).click();
    for (const { category, title, widgets } of [
      {
        category: 'generic',
        title: labelGenericWidgets,
        widgets: ['Clock / Timer', 'Generic text', 'Web page']
      },
      {
        category: 'realtime',
        title: labelRealTimeWidgets,
        widgets: [
          'Group monitoring',
          'Metrics graph',
          'Resource table',
          'Single metric',
          'Status chart',
          'Status grid',
          'Top/bottom'
        ]
      }
    ]) {
      const container = page.getByTestId(`${category}-accordion`);
      await expect(container.getByText(title)).toBeAttached();
      await expect(
        container.getByTestId(`${category}-summary`)
      ).toHaveAttribute('aria-expanded', 'true');
      for (const widget of widgets) {
        await expect(
          container.getByText(widget, { exact: true })
        ).toBeAttached();
      }
    }
    await expectThemedScreenshot(page, 'add-edit-widget-modal-categories');
  });
});

test.describe('Edit widget', () => {
  test.beforeEach(async ({ mount }) => {
    await open(mount, { initialForm: 'edit' });
  });

  test('displays the modal with the widget values', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect(page.getByText(labelEditWidget)).toBeVisible();
    await expect(page.getByLabel(labelWidgetType).first()).toHaveValue(
      'Generic text (example)'
    );
    await expect(titleField(page)).toHaveValue('Widget name');
    await expect(editor(page, 0)).toContainText('Description');
    await expect(editor(page, 1)).toContainText('Description');
    await expect(page.getByText('Widget name').first()).toBeAttached();
    await expect(saveButton(page)).toBeDisabled();
    await expectThemedScreenshot(page, 'add-edit-widget-modal-edition');
  });

  test('changes the widget type', async ({ page }) => {
    await pickWidget(page, 'Generic input (example)');
    await titleField(page).fill('Edited widget name');
    await page.getByLabel('Generic text', { exact: true }).fill('Text');
    await expect(titleField(page)).toHaveValue('Edited widget name');
    await expect(saveButton(page)).toBeEnabled();
  });
});

test.describe('Widget properties', () => {
  test.beforeEach(async ({ mount }) => {
    await open(mount);
  });

  const typeRichText = async (page: Page): Promise<void> => {
    const description = editor(page, 0);
    const toggleBold = async (): Promise<void> => {
      await page.getByLabel('format').click();
      await page.getByLabel('bold').click();
    };
    await description.pressSequentially('Hello ');
    await toggleBold();
    await description.pressSequentially('World');
    await toggleBold();
    await description.press('Enter');
    await description.pressSequentially('Hello!');
    await description.press('Enter');
    await description.pressSequentially('https://centreon.com');
    // Closes the format menu, still open over the form, from its backdrop.
    await page.mouse.click(5, 5);
  };

  test('previews the generic text widget content', async ({
    expectThemedScreenshot,
    page
  }) => {
    await pickWidget(page, 'Generic text');
    await typeRichText(page);
    for (const value of ['Hello World', 'Hello!', 'https://centreon.com']) {
      await expect(editor(page, 0)).toContainText(value);
    }
    // The description editor and the widget preview.
    await expect(page.getByText('https://centreon.com')).toHaveCount(2);
    await expectThemedScreenshot(page, 'add-edit-widget-modal-rich-text');
  });

  test('hides the generic text preview when the description is hidden', async ({
    page
  }) => {
    await pickWidget(page, 'Generic text');
    await typeRichText(page);
    await expect(page.getByText('https://centreon.com')).toHaveCount(2);
    await page.getByLabel(labelShowDescription).click();
    for (const value of ['Hello!', 'https://centreon.com']) {
      await expect(page.getByText(value)).toHaveCount(1);
    }
  });

  test('lists every widget with its icon, title and description', async ({
    page
  }) => {
    await widgetType(page).click();
    for (const { description, icon, title } of [
      widgetTextProperties,
      widgetInputProperties,
      widgetDataProperties,
      widgetGenericTextProperties,
      widgetSingleDataProperties,
      widgetStatusGridProperties,
      widgetSingleMetricProperties,
      widgetGraphProperties,
      widgetTopBottomProperties
    ] as Array<{ description: string; icon?: string; title: string }>) {
      await expect(
        page.locator(`[data-icon="${icon ? '' : 'default-'}${title}"]`)
      ).toBeAttached();
      await expect(page.getByText(title, { exact: true })).toBeAttached();
      await expect(page.getByText(description).first()).toBeAttached();
    }
  });

  test('hides a property when an option matches its condition', async ({
    page
  }) => {
    await pickWidget(page, 'Generic data (example)');
    await expect(page.getByText('Sort by')).toBeAttached();
    await page.getByLabel('Show thresholds').click();
    await expect(page.getByText('Sort by')).toHaveCount(0);
  });

  test('displays the general properties of the widget', async ({
    expectThemedScreenshot,
    page
  }) => {
    await pickWidget(page, 'Generic data (example)');
    await page.getByText('General properties').click();
    await expect(page.getByText('Group name')).toBeAttached();
    await expect(page.getByText('Select field')).toBeAttached();
    await expectThemedScreenshot(page, 'add-edit-widget-modal-general');
  });

  test('displays the sub inputs of the selected value', async ({ page }) => {
    await pickWidget(page, 'Generic data (example)');
    await page.getByText('General properties').click();
    await page.getByLabel('Button 3').click();
    await expect(page.getByLabel('Sub input 1')).toHaveValue('sample');
    await expect(page.getByLabel('Sub input 2')).toHaveValue('text');
    await page.getByLabel('Button 4').click();
    await expect(
      page.getByRole('radio', { exact: true, name: 'Radio 1' }).first()
    ).toBeChecked();
  });

  test('keeps a sub input value when it is hidden then displayed again', async ({
    page
  }) => {
    await pickWidget(page, 'Generic data (example)');
    await page.getByText('General properties').click();
    await page.getByLabel('Button 3').click();
    await expect(page.getByLabel('Sub input 1')).toHaveValue('sample');
    await page.getByLabel('Sub input 1').fill('updated value');
    await page.getByLabel('Button 2').click();
    await page.getByLabel('Button 3').click();
    await expect(page.getByLabel('Sub input 1')).toHaveValue('updated value');
  });
});

test('disables the generic properties outside the edition mode', async ({
  mockApi,
  mount,
  page
}) => {
  await mockData(mockApi);
  await open(mount, { initialForm: 'edit', isEditing: false });
  await expect(page.getByLabel(labelWidgetType).first()).toBeDisabled();
  await expect(titleField(page)).toBeDisabled();
  await expect(page.getByLabel(labelShowDescription)).toBeDisabled();
});

test('makes the description read-only outside the edition mode', async ({
  mockApi,
  mount,
  page
}) => {
  // Known app bug: the ContentEditable of @centreon/ui reads
  // editor.isEditable() while rendering, which React Compiler memoizes.
  // Cypress builds with coverage, without the compiler, so it passed there.
  test.fail();
  await mockData(mockApi);
  await open(mount, { initialForm: 'edit', isEditing: false });
  await expect(editor(page, 1)).toHaveAttribute('contenteditable', 'false');
  await expect(
    page.locator('[aria-label="RichTextEditor"][contenteditable="true"]')
  ).toHaveCount(0);
});

const typeField = (page: Page, index = 0) =>
  page.getByTestId(labelResourceType).nth(index);
const resourceField = (page: Page) => page.getByTestId(labelSelectAResource);
const addFilter = (page: Page) => page.getByLabel(labelAddFilter);

const pickHost = async (page: Page, hosts = ['Host 0']): Promise<void> => {
  await typeField(page).locator('..').click();
  await page.getByRole('option', { exact: true, name: 'Host' }).click();
  await resourceField(page).click();
  for (const host of hosts) {
    await page.getByRole('option', { exact: true, name: host }).click();
  }
};

const dashboardLayout = async (
  page: Page
): Promise<Array<{ data: Record<string, Array<Record<string, unknown>>> }>> =>
  JSON.parse(await page.getByTestId('dashboard-layout').inputValue());

test.describe('Resources and metrics', () => {
  test.beforeEach(async ({ mockApi, mount }) => {
    await mockData(mockApi);
    await open(mount);
  });

  test('does not suggest a resource type already used', async ({ page }) => {
    await pickWidget(page, 'Generic data (example)');
    await pickHost(page);
    await addFilter(page).click();
    await typeField(page, 1).locator('..').click();
    await expect(page.getByRole('option')).not.toHaveCount(0);
    await expect(page.getByText('Host', { exact: true })).toHaveCount(1);
  });

  test('removes a resource when its delete icon is clicked', async ({
    page
  }) => {
    await pickWidget(page, 'Generic data (example)');
    await pickHost(page);
    await resourceField(page).click();
    await expect(page.getByText('Host 0', { exact: true })).toHaveCount(1);
    await page.getByTestId('CancelIcon').click();
    await expect(page.getByText('Host 0', { exact: true })).toHaveCount(0);
  });

  const selectMetrics = async (page: Page, ids: Array<string>) => {
    await expect.poll(() => metrics.urls.length).toBeGreaterThan(0);
    await page.getByTestId(labelSelectMetric).click();
    for (const id of ids) {
      await page.getByTestId(id).click();
    }
  };

  test('selects the metrics of the selected resources', async ({
    expectThemedScreenshot,
    page
  }) => {
    await pickWidget(page, 'Generic data (example)');
    await titleField(page).fill('Generic data');
    await expect(addFilter(page)).toBeDisabled();
    await expect(saveButton(page)).toBeDisabled();
    await typeField(page).locator('..').click();
    await page.getByRole('option', { exact: true, name: 'Host' }).click();
    await resourceField(page).click();
    await expect(addFilter(page)).toBeDisabled();
    await page.getByRole('option', { exact: true, name: 'Host 0' }).click();
    await expect(addFilter(page)).toBeEnabled();
    await selectMetrics(page, ['pl', 'rtmax']);
    await page.getByTestId(labelSelectMetric).click();
    await expect(page.getByText('Metrics (4 available)')).toBeVisible();
    await expect(saveButton(page)).toBeEnabled();
    await expect(page.getByLabel(labelDelete).first()).toBeHidden();
    await expectThemedScreenshot(page, 'add-edit-widget-modal-metrics');
  });

  test('saves the excluded metric of a resource', async ({ page }) => {
    await pickWidget(page, 'Generic data (example)');
    await pickHost(page);
    await selectMetrics(page, ['pl']);
    await page.getByTestId('pl-summary').click();
    await page.getByTestId('pl_Centreon-1:Ping').click();
    await page.getByTestId(labelSelectMetric).click();
    await saveButton(page).click();
    await expect
      .poll(async () => (await dashboardLayout(page))[0]?.data.metrics[0])
      .toEqual({
        criticalHighThreshold: 1000,
        criticalLowThreshold: null,
        excludedMetrics: [2],
        id: 2,
        includeAllMetrics: true,
        name: 'pl',
        serviceId: 1,
        serviceName: 'Ping',
        unit: '%',
        warningHighThreshold: null,
        warningLowThreshold: null
      });
  });

  test('saves the selected resource and metric', async ({ page }) => {
    await pickWidget(page, 'Generic data (example)');
    await titleField(page).fill('Generic data');
    await pickHost(page);
    await selectMetrics(page, ['rtmax']);
    await page.getByTestId(labelSelectMetric).click();
    await saveButton(page).click();
    await expect.poll(async () => (await dashboardLayout(page)).length).toBe(1);
    const [widget] = await dashboardLayout(page);
    expect(widget.data.resources).toHaveLength(1);
    expect(widget.data.resources[0].resourceType).toBe('host');
    expect(widget.data.resources[0].resources).toHaveLength(1);
    expect(widget.data.metrics).toHaveLength(1);
  });

  test('selects one metric when the widget allows only one', async ({
    expectThemedScreenshot,
    page
  }) => {
    await pickWidget(page, 'Generic data for single metric (example)');
    await pickHost(page);
    await expect(addFilter(page)).toBeEnabled();
    await selectMetrics(page, ['pl']);
    await expectThemedScreenshot(page, 'add-edit-widget-modal-single-metric');
  });

  test('drops the metrics of a removed resource', async ({ mockApi, page }) => {
    await pickWidget(page, 'Generic data (example)');
    await titleField(page).fill('Generic data');
    await pickHost(page, ['Host 1', 'Host 2']);
    await selectMetrics(page, ['pl']);
    await page.getByTestId(labelSelectMetric).click();
    await expect(page.getByText('pl (%)/2')).toBeVisible();
    await mockApi({ endpoint: metricsEndpoint, response: serviceMetric });
    await page
      .getByText('Host 2', { exact: true })
      .locator('..')
      .getByTestId('CancelIcon')
      .click();
    await expect(page.getByText('pl (%)/1')).toBeVisible();
    await page.getByTestId(labelSelectMetric).click();
    await page.getByTestId('pl-summary').click();
    await expect(page.getByTestId('pl')).toHaveAttribute(
      'data-checked',
      'true'
    );
    await expect(page.getByTestId('pl_Centreon-1:Ping')).toHaveAttribute(
      'data-checked',
      'true'
    );
  });

  test('keeps the metrics field for a meta service', async ({ page }) => {
    await pickWidget(page, 'Generic data for single metric (example)');
    await expect(page.getByText(labelMetrics, { exact: true })).toBeVisible();
    await typeField(page).locator('..').click();
    await page
      .getByRole('option', { exact: true, name: 'Meta service' })
      .click();
    await resourceField(page).click();
    await page.getByRole('option', { name: 'Meta service 0' }).click();
    await expect(page.getByText(labelMetrics).first()).toBeVisible();
  });
});

test('selects the single metric of a service', async ({
  mockApi,
  mount,
  page
}) => {
  await mockData(mockApi, serviceMetric);
  await open(mount);
  await pickWidget(page, 'Generic data for single metric (example)');
  await pickHost(page);
  await expect(addFilter(page)).toBeEnabled();
  await expect.poll(() => metrics.urls.length).toBeGreaterThan(0);
  await page.getByTestId(labelSelectMetric).click();
  await page.getByText('pl (%)').click();
});

test('disables the data fields outside the edition mode', async ({
  mockApi,
  mount,
  page
}) => {
  await mockData(mockApi);
  await open(mount, { initialForm: 'data', isEditing: false });
  await expect(typeField(page)).toBeDisabled();
  await expect(page.getByLabel(labelSelectAResource)).toBeDisabled();
  await expect(page.getByLabel(labelSelectMetric)).toBeDisabled();
  await expect(page.getByText(labelAddFilter)).toHaveCount(0);
  await expect(page.getByText(labelAddMetric)).toHaveCount(0);
});

test('lists no widget when none is registered', async ({ mount, page }) => {
  await open(mount, { widgets: 'none' });
  await widgetType(page).click();
  await expect(page.getByText('No options')).toBeVisible();
});

test('skips a widget property of an unknown type', async ({ mount, page }) => {
  await open(mount, { widgets: 'unknownProperty' });
  await widgetType(page).click();
  await page.getByText('Generic data').click();
  await expect(page.getByTestId('unknown widget property')).toBeAttached();
});
