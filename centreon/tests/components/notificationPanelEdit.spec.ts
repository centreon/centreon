import type { Locator, Page } from '@playwright/test';

import {
  labelActiveOrInactive,
  labelBusinessViewsEvents,
  labelCancel,
  labelChangeName,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneContact,
  labelClosePanel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelDiscard,
  labelDuplicate,
  labelExpandInformationPanel,
  labelMessageFieldShouldNotBeEmpty,
  labelNotificationDuplicated,
  labelNotificationName,
  labelNotificationSuccessfullyDeleted,
  labelPleaseEnterNameForDuplicatedNotification,
  labelReduceInformationPanel,
  labelRequired,
  labelSave,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSubject,
  labelSuccessfulEditNotification,
  labelThisNameAlreadyExists,
  labelTimePeriod,
  labelTimePeriodFieldShouldNotBeEmpty
} from '../../www/front_src/src/CloudNotificationsConfiguration/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from CloudNotificationsConfiguration/Panel/specs/PanelEdit.
const storyId = 'Panel/NotificationPanel/Edit';
const notificationEndpoint = './api/latest/configuration/notifications';

interface PanelData {
  notification: unknown;
  notificationWithBam: unknown;
  timePeriodsResponse: unknown;
}

let data: PanelData;

test.use({ viewport: { height: 590, width: 1280 } });

const mockNotification = async (
  mockApi: (options: Record<string, unknown>) => Promise<unknown>,
  isBamModuleInstalled: boolean
): Promise<void> => {
  await mockApi({
    endpoint: `${notificationEndpoint}/1`,
    response: isBamModuleInstalled
      ? data.notificationWithBam
      : data.notification
  });
  await mockApi({
    endpoint: './api/latest/configuration/timeperiods',
    response: data.timePeriodsResponse
  });
};

test.beforeEach(async ({ loadStoryData }) => {
  data = await loadStoryData<PanelData>(
    'Panel/NotificationPanel/Data',
    'notificationPanelData'
  );
});

const clickOutside = (page: Page) => page.mouse.click(1, 1);
const clear = (component: Locator, index: number) =>
  // The autocomplete clear buttons only show on hover, like the Cypress force click.
  component
    .getByLabel('Clear', { exact: true })
    .nth(index)
    .click({ force: true });
const checkboxes = (component: Locator, testId: string) =>
  component.getByTestId(testId).getByRole('checkbox');

const expectChecked = async (boxes: Locator, states: Array<boolean>) => {
  await expect(boxes).toHaveCount(states.length);
  for (const [index, checked] of states.entries()) {
    if (checked) {
      await expect(boxes.nth(index)).toBeChecked();
    } else {
      await expect(boxes.nth(index)).not.toBeChecked();
    }
  }
};

const expectAll = async (boxes: Locator, disabled: boolean) => {
  for (const box of await boxes.all()) {
    await expect(box).not.toBeChecked();
    if (disabled) {
      await expect(box).toBeDisabled();
    }
  }
};

const loaded = async (component: Locator) => {
  await expect(component.getByText('Notifications 1').first()).toBeVisible();
};

test.describe('without BAM', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockNotification(mockApi as never, false);
  });

  test('displays the header actions', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);
    await loaded(component);

    for (const label of [
      labelDeleteNotification,
      labelSave,
      labelDuplicate,
      labelActiveOrInactive,
      labelClosePanel
    ]) {
      await expect(component.getByLabel(label).first()).toBeVisible();
    }

    await expectThemedScreenshot(component, 'notification-panel-edit');
  });

  test('renames the notification', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.getByTestId(labelChangeName).click();
    const name = component.getByLabel(labelNotificationName);
    await expect(name).toHaveValue('Notifications 1');
    await expect(name).toHaveAttribute('required', '');

    await name.clear();
    await clickOutside(page);
    await expect(component.getByText(labelRequired)).toBeVisible();
    await expect(component.getByLabel(labelSave)).toBeDisabled();

    await name.fill('Notifications 2');
    await clickOutside(page);
    await expect(component.getByText(labelThisNameAlreadyExists)).toBeVisible();
    await expect(component.getByLabel(labelSave)).toBeDisabled();
  });

  test('reduces and expands the panel, saving its width', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId);
    await loaded(component);
    const width = () =>
      page.evaluate(() =>
        localStorage.getItem('cloud-notifications-panel-width')
      );

    await expect(
      component.getByText(labelReduceInformationPanel)
    ).toBeVisible();
    await component.getByTestId(labelReduceInformationPanel).click();
    await expect(
      component.getByText(labelExpandInformationPanel)
    ).toBeVisible();
    await expect.poll(width).toBe('550');

    await component.getByTestId(labelReduceInformationPanel).click();
    await expect(
      component.getByText(labelReduceInformationPanel)
    ).toBeVisible();
    await expect.poll(width).toBe('800');
  });

  test('enables the save button once a field changes', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);
    const save = component.getByLabel(labelSave);

    await expect(save).toBeDisabled();
    await component.getByLabel(labelActiveOrInactive).click();
    await expect(save).toBeEnabled();
  });

  test('displays the host groups and their events', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await expect(component.getByLabel(labelSearchHostGroups)).toBeVisible();
    for (const group of ['Firewall', 'Linux-Servers', 'Networks', 'Printers']) {
      await expect(component.getByText(group, { exact: true })).toBeVisible();
    }
    await expectChecked(checkboxes(component, 'Host groups events'), [
      true,
      true,
      false
    ]);
    await expect(
      component.getByTestId('include Services').getByRole('checkbox')
    ).toBeChecked();
    await expectChecked(checkboxes(component, 'Extra events services'), [
      true,
      true,
      false,
      false
    ]);
  });

  test('disables the services events without "Include services"', async ({
    mount
  }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.getByTestId('include Services').click();

    await expectAll(checkboxes(component, 'Extra events services'), true);
  });

  test('hides the host group events once the host groups are cleared', async ({
    mount
  }) => {
    const component = await mount(storyId);
    await loaded(component);
    await expect(component.getByTestId('include Services')).toBeVisible();

    for (let removal = 0; removal < 3; removal += 1) {
      await clear(component, 0);
    }

    await expectAll(checkboxes(component, 'Host groups events'), false);
    await expect(component.getByTestId('include Services')).toHaveCount(0);
    await expect(component.getByTestId('Extra events services')).toHaveCount(0);
  });

  test('displays the service groups and their events', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await expect(component.getByLabel(labelSearchServiceGroups)).toBeVisible();
    for (const group of ['service1', 'service2', 'service3']) {
      await expect(component.getByText(group, { exact: true })).toBeVisible();
    }
    await expectChecked(checkboxes(component, 'Service groups events'), [
      false,
      true,
      true,
      true
    ]);
  });

  test('disables the service group events once cleared', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await clear(component, 1);

    await expectAll(checkboxes(component, 'Service groups events'), true);
  });

  test('requires at least one resource', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await clear(component, 0);
    await clear(component, 0);
    await component.getByTestId(labelSearchHostGroups).click();

    await expect(
      component.getByText(labelChooseAtLeastOneResource)
    ).toHaveCount(2);
    await expect(component.getByLabel(labelSave)).toBeDisabled();
  });

  test('displays the contacts', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);
    const contacts = component.getByTestId('Search contacts').locator('..');

    await expect(contacts.getByText('centreon-gorgone')).toBeAttached();
    await expect(contacts.getByText('Guest')).toBeAttached();
  });

  const requiredSelects = [
    {
      clearIndex: 3,
      error: labelChooseAtleastOneContact,
      field: labelSearchContacts,
      name: 'contact'
    },
    {
      clearIndex: 2,
      error: labelTimePeriodFieldShouldNotBeEmpty,
      field: labelTimePeriod,
      name: 'time period'
    }
  ];

  for (const { clearIndex, error, field, name } of requiredSelects) {
    test(`requires a ${name}`, async ({ mount, page }) => {
      const component = await mount(storyId);
      await loaded(component);

      await clear(component, clearIndex);
      await component.getByTestId(field).click();
      await clickOutside(page);

      await expect(component.getByText(error)).toHaveCount(1);
      await expect(component.getByLabel(labelSave)).toBeDisabled();
    });
  }

  test('only enables the email channel', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);
    const channel = (name: string) =>
      component.getByTestId(name).getByRole('checkbox');

    await expect(channel('Email')).toBeChecked();
    for (const disabled of ['SMS', 'Slack']) {
      await expect(channel(disabled)).not.toBeChecked();
      await expect(channel(disabled)).toBeDisabled();
    }
  });

  test('requires the email subject and message', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);
    const subject = component.getByLabel(labelSubject);

    await expect(subject).toHaveValue('Notification');
    await expect(component.getByTestId('EmailBody')).toContainText('Hello');

    await subject.clear();
    await clickOutside(page);
    await expect(component.getByText(labelRequired)).toBeVisible();
    await expect(component.getByLabel(labelSave)).toBeDisabled();
  });

  test('requires the email message', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);
    const body = component.getByTestId('EmailBody');

    await body.click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.press('Backspace');
    await clickOutside(page);

    await expect(
      component.getByText(labelMessageFieldShouldNotBeEmpty)
    ).toBeVisible();
    await expect(component.getByLabel(labelSave)).toBeDisabled();
  });

  test('saves the edited notification', async ({ mockApi, mount, page }) => {
    const update = await mockApi({
      endpoint: `${notificationEndpoint}/1`,
      method: 'PUT',
      response: { status: 'ok' }
    });
    const component = await mount(storyId);
    await loaded(component);

    await component.getByLabel(labelActiveOrInactive).click();
    await component.getByLabel(labelSave).click();

    await expect.poll(() => update.bodies.length).toBe(1);
    expect(update.bodies[0]).toMatchObject({ is_activated: true });
    await expect(page.getByText(labelSuccessfulEditNotification)).toBeVisible();
  });

  test.describe('deletion', () => {
    test('asks for a confirmation naming the notification', async ({
      mount,
      page
    }) => {
      const component = await mount(storyId);
      await loaded(component);

      await component.getByTestId(labelDeleteNotification).click();

      const dialog = page.getByRole('dialog');
      await expect(
        dialog.getByText(`${labelDelete} « Notifications 1 ».`)
      ).toBeVisible();
      await expect(dialog.getByText(labelDeleteNotification)).toBeVisible();
      await expect(
        dialog.getByText(labelDeleteNotificationWarning)
      ).toBeVisible();
      await dialog.getByText(labelCancel).click();
    });

    const outcomes = [
      {
        message: labelNotificationSuccessfullyDeleted,
        response: { data: [{ status: 204 }] },
        status: 200
      },
      {
        message: 'internal server error',
        response: { code: '500', message: 'internal server error' },
        status: 500
      }
    ];

    for (const { message, response, status } of outcomes) {
      test(`reports a ${status === 200 ? 'successful' : 'failed'} deletion`, async ({
        mockApi,
        mount,
        page
      }) => {
        const deletion = await mockApi({
          endpoint: `${notificationEndpoint}/1`,
          method: 'DELETE',
          response,
          status
        });
        const component = await mount(storyId);
        await loaded(component);

        await component.getByTestId(labelDeleteNotification).click();
        await page.getByRole('dialog').getByLabel(labelDelete).click();

        await expect.poll(() => deletion.urls.length).toBe(1);
        await expect(page.getByText(message)).toBeVisible();
      });
    }
  });

  test.describe('duplication', () => {
    test('is disabled once the form changes', async ({ mount }) => {
      const component = await mount(storyId);
      await loaded(component);
      const duplicate = component.getByTestId(labelDuplicate);

      await expect(duplicate).toBeEnabled();
      await clear(component, 0);
      await expect(duplicate).toBeDisabled();
    });

    test('validates the name of the copy', async ({ mount, page }) => {
      const component = await mount(storyId);
      await loaded(component);

      await component.getByTestId(labelDuplicate).click();
      const dialog = page.getByRole('dialog');
      const name = dialog.getByLabel(labelNotificationName);
      const confirm = dialog.getByTestId('Confirm');
      await expect(
        dialog.getByText(labelPleaseEnterNameForDuplicatedNotification)
      ).toBeVisible();
      await expect(name).toHaveAttribute('required', '');
      await expect(confirm).toBeDisabled();

      await name.fill('Notifications 2');
      await clickOutside(page);
      await expect(dialog.getByText(labelThisNameAlreadyExists)).toBeVisible();
      await expect(confirm).toBeDisabled();

      await name.clear();
      await clickOutside(page);
      await expect(dialog.getByText(labelRequired)).toBeVisible();
      await expect(confirm).toBeDisabled();
      await dialog.getByText(labelDiscard).click();
    });

    const outcomes = [
      {
        message: labelNotificationDuplicated,
        response: { status: 'ok' },
        status: 200
      },
      {
        message: 'internal server error',
        response: { code: '500', message: 'internal server error' },
        status: 500
      }
    ];

    for (const { message, response, status } of outcomes) {
      test(`reports a ${status === 200 ? 'successful' : 'failed'} duplication`, async ({
        mockApi,
        mount,
        page
      }) => {
        const duplication = await mockApi({
          endpoint: notificationEndpoint,
          method: 'POST',
          response,
          status
        });
        const component = await mount(storyId);
        await loaded(component);

        await component.getByTestId(labelDuplicate).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel(labelNotificationName).fill('New name');
        await dialog.getByTestId('Confirm').click();

        await expect.poll(() => duplication.urls.length).toBe(1);
        await expect(page.getByText(message)).toBeVisible();
      });
    }
  });
});

test.describe('with BAM', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockNotification(mockApi as never, true);
  });

  const props = { isBamModuleInstalled: true };

  test('displays the business views and their events', async ({ mount }) => {
    const component = await mount(storyId, props);

    await expect(component.getByTestId(labelSearchBusinessViews)).toBeVisible();
    for (const view of ['bv1', 'bv2']) {
      await expect(component.getByText(view, { exact: true })).toBeVisible();
    }
    await expectChecked(checkboxes(component, labelBusinessViewsEvents), [
      false,
      true,
      true,
      true
    ]);
  });

  test('disables the business view events once cleared', async ({ mount }) => {
    const component = await mount(storyId, props);
    await expect(component.getByText('bv1', { exact: true })).toBeVisible();

    await clear(component, 2);

    await expectAll(checkboxes(component, labelBusinessViewsEvents), true);
  });

  test('requires at least one of the three resources', async ({ mount }) => {
    const component = await mount(storyId, props);
    await expect(component.getByText('bv1', { exact: true })).toBeVisible();

    for (let removal = 0; removal < 3; removal += 1) {
      await clear(component, 0);
    }
    await component
      .getByTestId(labelSearchServiceGroups)
      .click({ force: true });

    await expect(
      component.getByText(labelChooseAtLeastOneResource)
    ).toHaveCount(3);
    await expect(component.getByLabel(labelSave)).toBeDisabled();
  });
});
