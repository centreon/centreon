import type { Locator } from '@playwright/test';

import {
  defaultPasswordSecurityPolicyAPI,
  defaultPasswordSecurityPolicyWithNullValues,
  retrievedPasswordSecurityPolicyAPI
} from '../../www/front_src/src/Authentication/Local/defaults';
import {
  labelChooseADurationBetween1HourAnd1Week,
  labelChooseADurationBetween7DaysAnd12Months,
  labelChooseAValueBetween1and10,
  labelDay,
  labelDays,
  labelDefinePasswordPasswordSecurityPolicy,
  labelDoYouWantToResetTheForm,
  labelExcludedUsers,
  labelGood,
  labelHour,
  labelLast3PasswordsCanBeReused,
  labelMinimumPasswordLength,
  labelMinimumTimeBetweenPasswordChanges,
  labelMinutes,
  labelMonth,
  labelNumberOfAttemptsBeforeUserIsBlocked,
  labelPasswordBlockingPolicy,
  labelPasswordCasePolicy,
  labelPasswordExpirationPolicy,
  labelPasswordExpiresAfter,
  labelPasswordMustContainLowerCase,
  labelPasswordMustContainNumbers,
  labelPasswordMustContainSpecialCharacters,
  labelPasswordMustContainUpperCase,
  labelReset,
  labelResetTheForm,
  labelSave,
  labelStrong,
  labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined,
  labelTimeThatMustPassBeforeNewConnection,
  labelWeak
} from '../../www/front_src/src/Authentication/Local/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Authentication/Local/LocalAuthentication.
const storyId = 'Local/LocalAuthentication/Default';
const endpoint = './api/latest/administration/authentication/providers/local';

test.use({ viewport: { height: 1000, width: 1200 } });

const minimumLength = (component: Locator): Locator =>
  component.getByLabel(labelMinimumPasswordLength);
const attempts = (component: Locator): Locator =>
  component.getByLabel(labelNumberOfAttemptsBeforeUserIsBlocked);
// Duration selects are comboboxes named through aria-labelledby.
const duration = (component: Locator, name: string): Locator =>
  component.getByRole('combobox', { exact: true, name });
const caseToggle = (component: Locator, label: string): Locator =>
  component.getByLabel(label).first();

test.describe('with the default policy', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({ endpoint, response: defaultPasswordSecurityPolicyAPI });
  });

  test('displays the policy sections', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);

    for (const label of [
      labelDefinePasswordPasswordSecurityPolicy,
      labelPasswordCasePolicy,
      labelPasswordExpirationPolicy,
      labelPasswordBlockingPolicy
    ]) {
      await expect(component.getByText(label, { exact: true })).toBeVisible();
    }
    await expect(minimumLength(component)).toHaveValue('12');

    await expectThemedScreenshot(component, 'local-authentication');
  });

  test('saves a modified policy', async ({ mockApi, mount }) => {
    const save = await mockApi({ endpoint, method: 'PUT', status: 204 });
    const component = await mount(storyId);
    await expect(minimumLength(component)).toHaveValue('12');

    await minimumLength(component).fill('45');
    await component.getByTestId(labelSave).click();

    await expect.poll(() => save.bodies.length).toBe(1);
    expect(save.bodies[0]).toMatchObject({
      password_security_policy: { password_min_length: 45 }
    });
  });

  test('resets the modified fields after confirmation', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId);
    const reset = component.getByRole('button', { name: labelReset });
    await expect(minimumLength(component)).toHaveValue('12');
    await expect(reset).toBeDisabled();

    await minimumLength(component).fill('45');
    await attempts(component).fill('8');
    await reset.click();

    const dialog = page.getByRole('dialog');
    await expect(
      dialog.getByRole('heading', { name: labelResetTheForm })
    ).toBeVisible();
    await expect(dialog.getByText(labelDoYouWantToResetTheForm)).toBeVisible();
    await dialog.getByLabel(labelReset).click();

    await expect(minimumLength(component)).toHaveValue('12');
  });

  test.describe('password case policy', () => {
    test('renders the case toggles and the length', async ({ mount }) => {
      const component = await mount(storyId);

      await expect(minimumLength(component)).toHaveValue('12');
      for (const label of [
        labelPasswordMustContainLowerCase,
        labelPasswordMustContainUpperCase,
        labelPasswordMustContainNumbers,
        labelPasswordMustContainSpecialCharacters
      ]) {
        await expect(caseToggle(component, label)).toBeVisible();
      }
      await expect(component.getByLabel(labelStrong).first()).toBeVisible();
    });

    test('updates the minimum length', async ({ mount }) => {
      const component = await mount(storyId);
      await expect(minimumLength(component)).toHaveValue('12');

      await minimumLength(component).fill('45');

      await expect(minimumLength(component)).toHaveValue('45');
    });
  });

  test.describe('password expiration policy', () => {
    test('renders the expiration and change delays', async ({ mount }) => {
      const component = await mount(storyId);

      await expect(
        duration(component, `${labelPasswordExpiresAfter} ${labelMonth}`)
      ).toBeVisible();
      await expect(
        duration(component, `${labelPasswordExpiresAfter} ${labelDays}`)
      ).toHaveText('7');
      await expect(
        duration(
          component,
          `${labelMinimumTimeBetweenPasswordChanges} ${labelHour}`
        )
      ).toHaveText('1');
      await expect(component.getByLabel(labelExcludedUsers)).toBeVisible();
    });

    const clearedDurations = [
      {
        error: labelChooseADurationBetween7DaysAnd12Months,
        field: `${labelPasswordExpiresAfter} ${labelDays}`
      },
      {
        error: labelChooseADurationBetween1HourAnd1Week,
        field: `${labelMinimumTimeBetweenPasswordChanges} ${labelHour}`
      }
    ];

    for (const { error, field } of clearedDurations) {
      test(`accepts an empty "${field}"`, async ({ mount }) => {
        const component = await mount(storyId);
        const select = duration(component, field);
        await expect(select).toBeVisible();

        await select.press('Backspace');

        await expect(component.getByText(error)).toHaveCount(0);
      });
    }

    test('allows reusing the last passwords', async ({ mount }) => {
      const component = await mount(storyId);
      const reuse = component.getByLabel(labelLast3PasswordsCanBeReused);

      await reuse.click();

      await expect(reuse).toBeChecked();
    });

    test('excludes a user picked from the contacts', async ({
      mockApi,
      mount,
      page
    }) => {
      await mockApi({
        endpoint: './api/latest/configuration/users',
        response: {
          meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 2 },
          result: [
            { alias: 'admin', email: 'admin@admin.com', id: 1, is_admin: true },
            { alias: 'user', email: 'user@admin.com', id: 2, is_admin: false }
          ]
        }
      });
      const component = await mount(storyId);

      await component.getByLabel(labelExcludedUsers).click();
      await page.getByRole('option', { name: 'admin' }).click();
      await page.keyboard.press('Escape');

      await expect(component.getByText('admin', { exact: true })).toHaveCount(
        1
      );
    });
  });

  test.describe('password blocking policy', () => {
    test('renders the attempts and the blocking duration', async ({
      mount
    }) => {
      const component = await mount(storyId);

      await expect(attempts(component)).toHaveValue('5');
      await expect(
        component.getByText(labelTimeThatMustPassBeforeNewConnection)
      ).toBeVisible();
      await expect(
        duration(
          component,
          `${labelTimeThatMustPassBeforeNewConnection} ${labelMinutes}`
        )
      ).toHaveText('15');
      await expect(component.getByText(labelWeak)).toBeVisible();
    });

    test('bounds the number of attempts between 1 and 10', async ({
      mount
    }) => {
      const component = await mount(storyId);
      const error = component.getByText(labelChooseAValueBetween1and10);

      await attempts(component).fill('50');
      await expect(error).toBeVisible();

      await attempts(component).fill('8');
      await expect(error).toHaveCount(0);

      await attempts(component).fill('811');
      await expect(error).toBeVisible();
    });

    test('warns that the duration is unused without attempts', async ({
      mount
    }) => {
      const component = await mount(storyId);
      await expect(attempts(component)).toHaveValue('5');

      await attempts(component).clear();

      await expect(
        component.getByText(
          labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined
        )
      ).toBeVisible();
    });

    test('rates the attempts', async ({ mount }) => {
      const component = await mount(storyId);

      await attempts(component).fill('2');
      await expect(component.getByText(labelStrong)).toHaveCount(2);

      await attempts(component).fill('4');
      await expect(component.getByText(labelGood).first()).toBeVisible();
    });

    test('rates the blocking duration', async ({ mount, page }) => {
      const component = await mount(storyId);
      const blocking = (unit: string): Locator =>
        duration(
          component,
          `${labelTimeThatMustPassBeforeNewConnection} ${unit}`
        );

      await blocking(labelDay).click();
      await page.getByRole('option', { exact: true, name: '6' }).click();
      await expect(component.getByText(labelStrong)).toHaveCount(2);

      await blocking(labelDays).click();
      await page.getByRole('option', { exact: true, name: '3' }).click();
      await blocking(labelMinutes).click();
      await page.getByRole('option', { exact: true, name: '0' }).click();

      await expect(component.getByText(labelGood)).toHaveCount(2);
    });
  });
});

test('saves the attempts of a retrieved policy', async ({ mockApi, mount }) => {
  await mockApi({ endpoint, response: retrievedPasswordSecurityPolicyAPI });
  const save = await mockApi({ endpoint, method: 'PUT', status: 204 });
  const component = await mount(storyId);
  const saveButton = component.getByTestId(labelSave);
  await expect(saveButton).toBeDisabled();

  await attempts(component).fill('2');
  await saveButton.click();

  await expect.poll(() => save.bodies.length).toBe(1);
  expect(save.bodies[0]).toMatchObject({
    password_security_policy: { attempts: 2 }
  });
});

test('rates the password cases', async ({ mockApi, mount }) => {
  await mockApi({
    endpoint,
    response: defaultPasswordSecurityPolicyWithNullValues
  });
  const component = await mount(storyId);
  await expect(minimumLength(component)).toBeVisible();

  for (const label of [
    labelPasswordMustContainLowerCase,
    labelPasswordMustContainUpperCase,
    labelPasswordMustContainNumbers,
    labelPasswordMustContainSpecialCharacters
  ]) {
    await caseToggle(component, label).click();
  }
  await expect(component.getByText(labelStrong).first()).toBeVisible();

  await caseToggle(
    component,
    labelPasswordMustContainSpecialCharacters
  ).click();
  await expect(component.getByText(labelGood).first()).toBeVisible();

  await caseToggle(component, labelPasswordMustContainNumbers).click();
  await expect(component.getByText(labelWeak).first()).toBeVisible();
});
