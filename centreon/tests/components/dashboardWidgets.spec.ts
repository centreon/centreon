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

const businessActivityWidgets = [
  {
    name: 'BA availability',
    storyId: 'centreon-widget-baavailability/src/Widget/Default'
  },
  {
    name: 'BA availability history',
    storyId: 'centreon-widget-baavailabilityhistory/src/Widget/Default'
  }
];

for (const { name, storyId } of businessActivityWidgets) {
  test.describe(`${name} widget`, () => {
    test('asks for resources when none is selected', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(storyId);

      await expect(component.getByText(labelPreviewRemainsEmpty)).toBeVisible();

      await expectThemedScreenshot(
        component,
        name.toLowerCase().replaceAll(' ', '-')
      );
    });

    test('does not ask for resources once a business activity is selected', async ({
      mount
    }) => {
      const component = await mount(storyId, {
        resources: [
          {
            resources: [{ id: 0, name: 'ba-1' }],
            resourceType: 'business-activity'
          }
        ]
      });

      await expect(component.getByText(labelPreviewRemainsEmpty)).toHaveCount(
        0
      );
    });
  });
}
