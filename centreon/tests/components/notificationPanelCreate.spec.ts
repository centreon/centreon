import type { Locator, Page } from '@playwright/test';

import {
  labelActiveOrInactive,
  labelChangeName,
  labelClosePanel,
  labelDelete,
  labelDoYouWantToQuitWithoutSaving,
  labelDuplicate,
  labelNotificationName,
  labelSave,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSelectTimePeriod,
  labelSubject,
  labelSuccessfulNotificationAdded,
  labelYourFormHasUnsavedChanges
} from '../../www/front_src/src/CloudNotificationsConfiguration/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from CloudNotificationsConfiguration/Panel/specs/PanelCreate.
const storyId = 'Panel/NotificationPanel/Create';

interface PanelData {
  defaultEmailSubject: string;
  emailBodyText: Array<string>;
  formData: unknown;
  hostGroupsResponse: unknown;
  serviceGroupsResponse: unknown;
  timePeriodsResponse: unknown;
  usersResponse: unknown;
}

let data: PanelData;
let creation: { bodies: Array<unknown> };

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ loadStoryData, mockApi }) => {
  data = await loadStoryData<PanelData>(
    'Panel/NotificationPanel/Data',
    'notificationPanelData'
  );
  creation = await mockApi({
    endpoint: './api/latest/configuration/notifications',
    method: 'POST',
    response: { status: 'ok' }
  });
  const lookups: Array<[string, unknown]> = [
    ['./api/latest/configuration/hosts/groups', data.hostGroupsResponse],
    ['./api/latest/configuration/services/groups', data.serviceGroupsResponse],
    ['./api/latest/configuration/users', data.usersResponse],
    ['./api/latest/configuration/timeperiods', data.timePeriodsResponse]
  ];
  for (const [endpoint, response] of lookups) {
    await mockApi({ endpoint, response });
  }
});

const pick = async (page: Page, field: Locator, option: string) => {
  await field.click();
  await page.getByRole('option', { name: option }).first().click();
};

const fillRequiredFields = async (component: Locator, page: Page) => {
  await component.getByLabel(labelNotificationName).fill('notification#1');
  await pick(page, component.getByLabel(labelSearchHostGroups), 'Firewall');
  await pick(
    page,
    component.getByLabel(labelSearchServiceGroups),
    'MySQL-Servers'
  );
  await pick(page, component.getByLabel(labelSelectTimePeriod), '24X7');
  await pick(page, component.getByLabel(labelSearchContacts), 'Guest');
  await page.keyboard.press('Escape');
};

test('displays an empty creation form', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(component.getByLabel(labelSave)).toBeDisabled();
  await expect(component.getByLabel(labelDelete)).toHaveCount(0);
  await expect(component.getByLabel(labelDuplicate)).toHaveCount(0);
  await expect(component.getByLabel(labelActiveOrInactive)).toBeEnabled();
  await expect(component.getByTestId(labelChangeName)).toHaveCount(0);
  const name = component.getByLabel(labelNotificationName);
  await expect(name).toHaveValue('');
  await expect(name).toHaveAttribute('required', '');

  await expectThemedScreenshot(component, 'notification-panel-create');
});

test('creates a notification from the required fields', async ({
  mount,
  page
}) => {
  const component = await mount(storyId);
  const save = component.getByLabel(labelSave);
  await expect(save).toBeDisabled();

  await fillRequiredFields(component, page);
  await expect(save).toBeEnabled();
  await save.click();

  await expect.poll(() => creation.bodies).toEqual([data.formData]);
  await expect(page.getByText(labelSuccessfulNotificationAdded)).toBeVisible();
});

test('asks before closing a modified form', async ({ mount, page }) => {
  const component = await mount(storyId);

  await pick(page, component.getByLabel(labelSearchHostGroups), 'Firewall');
  await page.keyboard.press('Escape');
  await component.getByLabel(labelClosePanel).click();

  await expect(page.getByText(labelYourFormHasUnsavedChanges)).toBeVisible();
  await expect(page.getByText(labelDoYouWantToQuitWithoutSaving)).toBeVisible();
});

test('prefills the email subject and body', async ({ mount }) => {
  const component = await mount(storyId);

  await expect(component.getByLabel(labelSubject)).toHaveValue(
    data.defaultEmailSubject
  );
  for (const text of data.emailBodyText) {
    await expect(component.getByTestId('EmailBody')).toContainText(text);
  }
});

test('offers business views when BAM is installed', async ({ mount }) => {
  const component = await mount(storyId, { isBamModuleInstalled: true });

  await expect(component.getByTestId(labelSearchBusinessViews)).toBeVisible();
});
