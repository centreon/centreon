import { labelWebPagePreview } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-webpage/src/translatedLabels';
import { labelPreviewRemainsEmpty } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from the Cypress specs of Dashboards/SingleInstancePage/Dashboard/Widgets.

test('text widget displays its content', async ({ mount }) => {
  const component = await mount('centreon-widget-text/src/Widget/Default');

  await expect(
    component.getByRole('heading', { name: 'Hello world' })
  ).toBeVisible();
  await expect(component.getByRole('paragraph')).toHaveText('Hello world');
});

test('input widget displays and updates its text', async ({ mount }) => {
  const component = await mount('centreon-widget-input/src/Widget/Default', {
    text: 'hello'
  });
  const input = component.getByRole('textbox');

  await expect(input).toHaveValue('hello');
  await input.fill('hello world');
  await expect(input).toHaveValue('hello world');
});

const host = {
  resources: [{ id: 0, name: 'my host' }],
  resourceType: 'host'
};
const businessActivity = {
  resources: [{ id: 0, name: 'ba-1' }],
  resourceType: 'business-activity'
};

// Widgets showing an empty preview until their resources (and metrics) are set.
const previewWidgets = [
  {
    complete: { resources: [businessActivity] },
    empty: [{ resources: [] }],
    name: 'BA availability',
    storyId: 'centreon-widget-baavailability/src/Widget/Default'
  },
  {
    complete: { resources: [businessActivity] },
    empty: [{ resources: [] }],
    name: 'BA availability history',
    storyId: 'centreon-widget-baavailabilityhistory/src/Widget/Default'
  },
  {
    complete: { metrics: [{ id: 0, name: 'metric' }], resources: [host] },
    empty: [
      { metrics: [], resources: [] },
      { metrics: [], resources: [host] }
    ],
    name: 'Near saturation storage',
    storyId: 'centreon-widget-mbinearsaturationstorage/src/Widget/Default'
  },
  {
    complete: { metrics: [{ id: 0, name: 'metric' }], resources: [host] },
    empty: [
      { metrics: [], resources: [] },
      { metrics: [], resources: [host] }
    ],
    name: 'Metric capacity planning',
    storyId: 'centreon-widget-metriccapacityplanning/src/Widget/Default'
  }
];

for (const { complete, empty, name, storyId } of previewWidgets) {
  test.describe(`${name} widget`, () => {
    empty.forEach((panelData, index) => {
      test(`asks for its data (${index + 1})`, async ({
        expectThemedScreenshot,
        mount
      }) => {
        const component = await mount(storyId, { panelData });

        await expect(
          component.getByText(labelPreviewRemainsEmpty)
        ).toBeVisible();

        if (index === 0) {
          await expectThemedScreenshot(
            component,
            name.toLowerCase().replaceAll(' ', '-')
          );
        }
      });
    });

    test('renders once its data is selected', async ({ mount }) => {
      const component = await mount(storyId, { panelData: complete });

      await expect(component.getByText(labelPreviewRemainsEmpty)).toHaveCount(
        0
      );
    });
  });
}

test.describe('Web page widget', () => {
  const storyId = 'centreon-widget-webpage/src/Widget/Default';

  // Keeps the specs hermetic: the iframe never reaches the real site.
  test.beforeEach(async ({ page }) => {
    await page.route('http://docs.centreon.com/**', (route) =>
      route.fulfill({ body: '<p>docs</p>', contentType: 'text/html' })
    );
  });

  test('displays the preview while the URL is empty', async ({ mount }) => {
    const component = await mount(storyId, { url: '' });

    await expect(component.getByTestId('Webpage Display')).toHaveCount(0);
    await expect(component.getByText(labelWebPagePreview)).toBeVisible();
  });

  for (const url of ['http://docs.centreon.com', 'docs.centreon.com']) {
    test(`embeds ${url} with an http prefix`, async ({ mount }) => {
      const component = await mount(storyId, { url, widgetId: '7' });
      const iframe = component.getByTestId('Webpage Display');

      await expect(iframe).toBeVisible();
      await expect(iframe).toHaveAttribute('src', 'http://docs.centreon.com');
      await expect(iframe).toHaveAttribute('id', 'Webpage_7');
    });
  }

  test('keeps the page across automatic refreshes', async ({ mount, page }) => {
    await page.clock.install();
    const component = await mount(storyId, {
      globalRefreshInterval: { interval: 1000, type: 'automatic' },
      url: 'docs.centreon.com'
    });
    const iframe = component.getByTestId('Webpage Display');

    for (let tick = 0; tick < 2; tick += 1) {
      await page.clock.runFor(1000);
      await expect(iframe).toHaveAttribute('src', 'http://docs.centreon.com');
    }
  });
});
