import type { Locator } from '@playwright/test';

import listOptions from '../../../packages/ui/cypress/fixtures/inputField/listOptions.json';
import searchedOption from '../../../packages/ui/cypress/fixtures/inputField/searchedOption.json';
import { expect, test } from '../fixtures';

/**
 * packages/ui MultiConnectedAutocompleteField and the exclusive checkbox group.
 */
test.describe('Multi connected autocomplete', () => {
  const story =
    'ui/InputField/Select/Autocomplete/Connected/Multi/MultiConnectedAutocompleteField/Default';

  test.beforeEach(async ({ mockApi }) => {
    // A search for the first option answers it alone.
    await mockApi({
      endpoint: 'endpoint',
      response: (url: URL) =>
        (url.searchParams.get('search') ?? '').includes('My Option 1')
          ? searchedOption
          : listOptions
    });
  });

  test('opens and closes the options on click', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(story);
    const input = component.getByTestId('Multi Connected Autocomplete');
    await expect(
      component.locator('label', { hasText: 'Multi Connected Autocomplete' })
    ).toBeVisible();
    await input.click();
    await expect(input).toHaveAttribute('placeholder', 'Type here...');
    const options = page.getByRole('presentation');
    for (const { name } of listOptions.result) {
      await expect(options.getByText(name)).toBeVisible();
    }
    await expectThemedScreenshot(page, 'multi-connected-autocomplete-open');
    await input.click();
    await expect(options).toHaveCount(0);
  });

  test('keeps every option after selecting a searched one', async ({
    mount,
    page
  }) => {
    const component = await mount(story);
    const input = component.getByTestId('Multi Connected Autocomplete');
    await input.click();
    const options = page.getByRole('presentation');
    await input.fill('My Option 1');
    // The searched option, plus the select all entry and the counter.
    await expect(options.locator('li')).toHaveCount(6);
    await options.locator('[type="checkbox"]').first().check();
    await expect(component.getByText('My Option 1')).toBeVisible();
    await expect(component.getByTestId('CancelIcon')).toBeVisible();
    await expect(options.locator('li')).toHaveCount(
      listOptions.result.length + 1
    );
    for (const { name } of listOptions.result) {
      await expect(options.getByText(name)).toBeVisible();
    }
  });

  test('selects and unselects every option at once', async ({
    mount,
    page
  }) => {
    const component = await mount(story);
    await component.getByTestId('Multi Connected Autocomplete').click();
    await expect(page.getByText('5 element(s) found')).toBeVisible();
    await page.getByText('Select all', { exact: true }).click();
    await expect(component.getByTestId('CancelIcon')).toHaveCount(5);
    await page.getByText('Unselect all', { exact: true }).click();
    await expect(page.getByText('Select all', { exact: true })).toBeVisible();
    await expect(component.getByTestId('CancelIcon')).toHaveCount(0);
  });
});

test.describe('Exclusive checkbox group', () => {
  const story = 'ui/Form/Inputs/ExclusiveCheckboxGroup/Default';

  test('goes between inherited, selected and exclusive states', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(story);
    const toggle = component.getByLabel('No notifications');
    const box = (label: string) => component.getByLabel(label, { exact: true });
    const value = component.getByTestId('value');

    await expect(toggle).not.toBeChecked();
    for (const option of ['Down', 'Unreachable', 'Recovery']) {
      await expect(box(option)).toBeEnabled();
    }
    await expect(box('None')).toHaveCount(0);
    await expect(value).toHaveText('[]');
    await expectThemedScreenshot(component, 'exclusive-checkbox-group');

    await box('Down').click();
    await box('Recovery').click();
    await expect(value).toHaveText('["Down","Recovery"]');
    await toggle.click();
    await expect(value).toHaveText('["None"]');
    await expect(box('Down')).not.toBeChecked();
    await expect(box('Down')).toBeDisabled();
    await box('Down').click({ force: true });
    await expect(value).toHaveText('["None"]');
    await toggle.click();
    await expect(value).toHaveText('[]');
    await box('Unreachable').click();
    await expect(value).toHaveText('["Unreachable"]');
    await box('Unreachable').click();
    await expect(value).toHaveText('[]');
  });

  for (const [initial, toggled, checked] of [
    [null, false, []],
    [['None'], true, []],
    [['Down', 'Recovery'], false, ['Down', 'Recovery']]
  ] as const) {
    test(`starts from ${JSON.stringify(initial)}`, async ({ mount }) => {
      const component = await mount(story, {
        notificationOptions: initial as Array<string> | null
      });
      const toggle = component.getByLabel('No notifications');
      await (toggled
        ? expect(toggle).toBeChecked()
        : expect(toggle).not.toBeChecked());
      for (const option of ['Down', 'Unreachable', 'Recovery']) {
        const box = component.getByLabel(option, { exact: true });
        await ((checked as ReadonlyArray<string>).includes(option)
          ? expect(box).toBeChecked()
          : expect(box).not.toBeChecked());
        await (toggled
          ? expect(box).toBeDisabled()
          : expect(box).toBeEnabled());
      }
    });
  }

  test('disables everything when disabled', async ({ mount }) => {
    const component = await mount(story, {
      disabled: true,
      notificationOptions: ['Down']
    });
    await expect(component.getByLabel('No notifications')).toBeDisabled();
    await expect(component.getByLabel('Down', { exact: true })).toBeChecked();
    await expect(component.getByLabel('Down', { exact: true })).toBeDisabled();
  });

  test.describe('as chips', () => {
    const chip = (component: Locator, option: string) =>
      component.getByTestId(`notification-options-${option}`);

    test('presses the chips, and releases them for the toggle', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(story, { variant: 'chips' });
      const value = component.getByTestId('value');
      for (const option of ['Down', 'Unreachable', 'Recovery']) {
        await expect(chip(component, option)).toHaveAttribute(
          'aria-pressed',
          'false'
        );
      }
      await expect(chip(component, 'None')).toHaveCount(0);
      await expect(
        component.getByRole('checkbox', { name: 'Down' })
      ).toHaveCount(0);
      await expectThemedScreenshot(component, 'exclusive-checkbox-group-chips');

      await chip(component, 'Down').click();
      await chip(component, 'Recovery').click();
      await expect(value).toHaveText('["Down","Recovery"]');
      await expect(chip(component, 'Down')).toHaveAttribute(
        'aria-pressed',
        'true'
      );
      await chip(component, 'Down').click();
      await expect(value).toHaveText('["Recovery"]');

      await component.getByLabel('No notifications').click();
      await expect(value).toHaveText('["None"]');
      await expect(chip(component, 'Recovery')).toHaveAttribute(
        'aria-pressed',
        'false'
      );
      await expect(chip(component, 'Recovery')).toHaveAttribute(
        'aria-disabled',
        'true'
      );
      await component.getByLabel('No notifications').click();
      await expect(value).toHaveText('[]');
      await chip(component, 'Unreachable').click();
      await expect(value).toHaveText('["Unreachable"]');
    });

    test('disables the chips and the toggle when disabled', async ({
      mount
    }) => {
      const component = await mount(story, {
        disabled: true,
        variant: 'chips'
      });
      await expect(chip(component, 'Down')).toHaveAttribute(
        'aria-disabled',
        'true'
      );
      await expect(component.getByLabel('No notifications')).toBeDisabled();
    });
  });
});
