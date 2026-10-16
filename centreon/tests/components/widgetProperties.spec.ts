import {
  labelDisplayUpTo,
  labelTiles
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/AddEditWidget/WidgetProperties/Inputs/Tiles/translatedLabels';
import {
  labelSelectTimeFormat,
  labelSelectTimezone
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from the Cypress specs of the widget property inputs
// (Dashboards/SingleInstancePage/Dashboard/AddEditWidget/WidgetProperties).

test.describe('Time format', () => {
  test('preselects the format of the user locale', async ({ mount }) => {
    const component = await mount('TimeFormat/TimeFormat/Default', {
      user: { locale: 'fr_FR' }
    });

    await expect(component.getByText('24 hours')).toHaveAttribute(
      'data-selected',
      'true'
    );
  });

  test('preselects the saved value', async ({ mount }) => {
    const component = await mount('TimeFormat/TimeFormat/Default', {
      options: { test: '12' },
      user: { locale: 'en_US' }
    });

    await expect(component.getByText('12 hours')).toHaveAttribute(
      'data-selected',
      'true'
    );
  });
});

test.describe('Tiles', () => {
  const storyId = 'Tiles/Tiles/Default';

  test('displays the tiles input with its initial value', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options: { tiles: 100 } });

    await expect(component.getByText(labelDisplayUpTo)).toBeVisible();
    await expect(component.getByLabel(labelTiles)).toHaveValue('100');

    await expectThemedScreenshot(component, 'tiles');
  });

  test('writes the typed value to the form', async ({ mount }) => {
    const component = await mount(storyId, { options: { tiles: 100 } });

    await component.getByLabel(labelTiles).fill('50');

    await expect(component.getByLabel(labelTiles)).toHaveValue('50');
    await expect(component.getByTestId('form-values')).toHaveValue(
      /"tiles":50/
    );
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, {
      canEdit: false,
      options: { tiles: 100 }
    });

    await expect(component.getByLabel(labelTiles)).toBeDisabled();
  });
});

const autocompletes = [
  {
    initial: 'Europe/Helsinki',
    name: 'Timezone',
    option: 'Africa/Cairo',
    selected: 'Africa/Cairo',
    storyId: 'Timezone/Timezone/Default',
    testId: labelSelectTimezone,
    user: { timezone: 'Europe/Helsinki' },
    value: { id: 'Europe/Helsinki', name: 'Europe/Helsinki' }
  },
  {
    initial: 'German (Germany) (de-DE)',
    name: 'Locale',
    option: 'English (United Kingdom)',
    selected: 'English (United Kingdom) (en-GB)',
    storyId: 'Locale/Locale/Default',
    testId: labelSelectTimeFormat,
    user: { locale: 'de-DE' },
    value: { id: 'de_DE', name: 'de_DE' }
  }
];

for (const autocomplete of autocompletes) {
  test.describe(autocomplete.name, () => {
    test('preselects the value of the user', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(autocomplete.storyId, {
        user: autocomplete.user
      });

      await expect(component.getByTestId(autocomplete.testId)).toHaveValue(
        autocomplete.initial
      );

      await expectThemedScreenshot(component, autocomplete.name.toLowerCase());
    });

    test('selects another value', async ({ mount, page }) => {
      const component = await mount(autocomplete.storyId, {
        options: { test: autocomplete.value },
        user: autocomplete.user
      });

      await component.getByTestId(autocomplete.testId).click();
      await page
        .getByRole('option', { name: autocomplete.option })
        .first()
        .click();

      await expect(component.getByTestId(autocomplete.testId)).toHaveValue(
        autocomplete.selected
      );
    });

    test('is disabled without edition rights', async ({ mount }) => {
      const component = await mount(autocomplete.storyId, {
        canEdit: false,
        options: { test: autocomplete.value },
        user: autocomplete.user
      });

      await expect(component.getByTestId(autocomplete.testId)).toBeDisabled();
    });
  });
}

test.describe('Widget message', () => {
  const message = {
    icon: '<path fill-rule="evenodd" d="M15.35 8c0 3.377-2.945 6.25-6.75 6.25S1.85 11.377 1.85 8 4.795 1.75 8.6 1.75 15.35 4.623 15.35 8zm1.25 0c0 4.142-3.582 7.5-8 7.5S.6 12.142.6 8C.6 3.858 4.182.5 8.6.5s8 3.358 8 7.5zM9.229 3.101l-.014 7.3-1.25-.002.014-7.3 1.25.002zm.016 9.249a.65.65 0 1 0-1.3 0 .65.65 0 0 0 1.3 0z"/>',
    label: 'Message to display bellow widget preview'
  };

  test('displays the message of the widget', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('WidgetMessage/WidgetMessage/Default', {
      extraValues: { message },
      widgetProperties: { message }
    });

    await expect(component.getByText(message.label)).toBeVisible();
    await expect(component.getByTestId('Message icon')).toBeVisible();

    await expectThemedScreenshot(component, 'widget-message');
  });

  test('displays nothing when the widget has no message', async ({ mount }) => {
    const component = await mount('WidgetMessage/WidgetMessage/Default');

    await expect(component.getByText(message.label)).toHaveCount(0);
    await expect(component.getByTestId('Message icon')).toHaveCount(0);
  });
});
