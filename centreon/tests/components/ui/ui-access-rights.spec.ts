import type { Locator, Page } from '@playwright/test';

import {
  buildResult,
  simpleAccessRights
} from '../../../packages/ui/src/components/Form/AccessRights/storiesData';
import { expect, type MockApi, test } from '../fixtures';

/**
 * packages/ui AccessRights: the list of the access rights, their removal and
 * role change, the contacts and contact groups added, and the statistics.
 * The Cypress scenarios each mounted the form; they are journeys here.
 */
const story = 'ui/components/Form/AccessRights/AccessRights/Default';
// The contacts of the directory are named "Entity  <n>", with two spaces.

const mockDirectory = async (mockApi: MockApi) => ({
  contactGroups: await mockApi({
    endpoint: 'contact-groups',
    response: buildResult(true)
  }),
  contacts: await mockApi({
    endpoint: 'contacts',
    response: buildResult(false)
  })
});

const pickRole = async (
  page: Page,
  select: Locator,
  role: string
): Promise<void> => {
  await select.locator('xpath=..').click();
  await page.locator(`li[data-value="${role}"]`).click();
};

const add = async (
  page: Page,
  component: Locator,
  kind: 'Contact' | 'Contact group',
  name: string,
  role?: string
) => {
  await component.getByText(kind, { exact: true }).click();
  await component
    .getByLabel(kind === 'Contact' ? 'Add a contact' : 'Add a contact group')
    .click();
  await page.getByText(name, { exact: true }).click();
  if (role) {
    await pickRole(page, component.getByTestId('add_role'), role);
  }
  await component.getByTestId('add').click();
};

test('lists the access rights, and their loading and empty states', async ({
  expectThemedScreenshot,
  mockApi,
  mount
}) => {
  await mockDirectory(mockApi);
  const loading = await mount(story, { loading: true });
  await expect(loading.getByText('Share dashboard with')).toBeVisible();
  await expect(loading.getByLabel('Add a contact')).toBeVisible();
  await expect(loading.getByTestId('add_role')).toBeDisabled();
  await expect(loading.getByTestId('add')).toBeDisabled();
  await expect(loading.getByLabel('Cancel')).toBeVisible();
  await expect(loading.getByLabel('Save')).toBeVisible();

  const empty = await mount(story, { empty: true });
  await expect(empty.getByText('The contact list is empty')).toBeVisible();

  const component = await mount(story);
  for (const { email, isContactGroup, name, role } of simpleAccessRights) {
    await expect(component.getByText(name, { exact: true })).toBeVisible();
    await expect(component.getByText(email ?? '', { exact: true })).toBeVisible();
    await expect(component.locator(`[data-groupChip="${name}"]`)).toHaveCount(
      isContactGroup ? 1 : 0
    );
    await expect(component.getByTestId(`role-${name}`)).toHaveValue(role);
    await expect(component.getByTestId(`remove-${name}`)).toBeVisible();
  }
  await expectThemedScreenshot(component, 'access-rights');
});

test('removes, restores and changes access rights, then saves', async ({
  mockApi,
  mount,
  page
}) => {
  await mockDirectory(mockApi);
  const component = await mount(story);
  const remove = component.getByTestId('remove-Erin Monahan');
  const role = component.getByTestId('role-Erin Monahan');

  await expect(remove).toHaveAttribute('data-removed', 'false');
  await remove.click();
  await expect(remove).toHaveAttribute('data-removed', 'true');
  await expect(component.getByText('Removed', { exact: true })).toBeVisible();
  await expect(component.getByText('1 removed')).toBeVisible();
  await remove.click();
  await expect(remove).toHaveAttribute('data-removed', 'false');
  await expect(component.getByText('Removed', { exact: true })).toHaveCount(0);

  await pickRole(page, role, 'viewer');
  await expect(component.getByText('Updated', { exact: true })).toBeVisible();
  await expect(component.getByText('1 updated')).toBeVisible();
  await pickRole(page, role, 'editor');
  await expect(component.getByText('Updated', { exact: true })).toHaveCount(0);

  await remove.click();
  await component.getByText('Save', { exact: true }).click();
  await expect(component.getByTestId('calls')).toContainText('"save":1');
});

test('adds contacts and contact groups, and counts the changes', async ({
  mockApi,
  mount,
  page
}) => {
  const directory = await mockDirectory(mockApi);
  const component = await mount(story);

  await add(page, component, 'Contact', 'Entity 10');
  await expect.poll(() => directory.contacts.urls.length).toBeGreaterThan(0);
  await expect(component.getByTestId('role-Entity  10')).toHaveValue('viewer');
  await expect(component.getByText('Added', { exact: true })).toBeVisible();

  await add(page, component, 'Contact group', 'Entity Group 10', 'editor');
  await expect
    .poll(() => directory.contactGroups.urls.length)
    .toBeGreaterThan(0);
  await expect(component.getByTestId('role-Entity Group 10')).toHaveValue(
    'editor'
  );
  await expect(component.getByText('2 added')).toBeVisible();

  await pickRole(page, component.getByTestId('role-Erin Monahan'), 'viewer');
  await component.getByTestId('remove-Maryann Ferry').click();
  await expect(
    component.getByText('2 added | 1 updated | 1 removed')
  ).toBeVisible();

  // A contact added then removed leaves the list.
  await component.getByTestId('remove-Entity  10').click();
  await expect(component.getByText('Entity 10', { exact: true })).toHaveCount(
    0
  );
  await expect(
    component.getByText('1 added | 1 updated | 1 removed')
  ).toBeVisible();
});

test('clears the picked contact when the kind changes, and resets the role', async ({
  mockApi,
  mount,
  page
}) => {
  await mockDirectory(mockApi);
  const component = await mount(story);
  const contact = component.getByLabel('Add a contact');

  await component.getByText('Contact', { exact: true }).click();
  await contact.click();
  await page.getByText('Entity 11', { exact: true }).click();
  await expect(contact).toHaveValue('Entity  11');
  await component.getByText('Contact group', { exact: true }).click();
  await expect(component.getByLabel('Add a contact group')).toHaveValue('');

  await component.getByText('Contact', { exact: true }).click();
  await contact.click();
  await page.getByText('Entity 11', { exact: true }).click();
  await pickRole(page, component.getByTestId('add_role'), 'editor');
  await contact.click();
  await page.getByText('Entity 12', { exact: true }).click();
  await expect(component.getByTestId('add_role')).toHaveValue('viewer');
});

test('calls back on each change', async ({ mockApi, mount, page }) => {
  await mockDirectory(mockApi);
  const component = await mount(story);
  await add(page, component, 'Contact', 'Entity 11');
  await expect(component.getByText('Added', { exact: true })).toBeVisible();
  await expect(component.getByTestId('calls')).toContainText('"change":2');
});
