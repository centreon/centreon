import type { Locator, Page } from '@playwright/test';

import {
  labelClose,
  labelFailedToUpdateSomeResources,
  labelMassChange,
  labelMoreActions
} from '../../www/front_src/src/Configuration/ConfigurationBase/translatedLabels';
import {
  labelDown,
  labelTemplateReplacementDeletesServices
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
import {
  type HostsApi,
  hostField,
  panelHeader,
  pickOptions,
  saveButton,
  setUpHosts
} from './hostsApi';

// Migrated from Configuration/Hosts/Specs/Hosts.MassChange.
test.use({ viewport: { height: 900, width: 1440 } });

// By its own checkbox: the Enable/Disable switches are checkboxes too.
const tickRow = (component: Locator, id: number) =>
  component.getByLabel(`Select row ${id}`, { exact: true }).click();
const openMassChange = async (component: Locator) => {
  await component.getByTestId(labelMoreActions).click();
  await component
    .page()
    .getByRole('menu')
    .getByText(labelMassChange, { exact: true })
    .click();
};
const typeMaxCheckAttempts = (page: Page, value: string) =>
  hostField(page, 'host-form-scheduling-options-maxCheckAttempts').fill(value);
const confirmation = (page: Page) =>
  page.getByTestId('mass-change-confirmation');
const warning = (page: Page) => page.getByTestId('mass-change-warning');
const expectHeaderCount = (page: Page, count: number) =>
  expect(panelHeader(page)).toContainText(`${labelMassChange} (${count})`);

// Exactly these hosts were written to, as many times, through API Platform.
const expectPatchedHosts = async (
  api: HostsApi,
  expectedIds: Array<number>,
  body?: object
) => {
  for (const id of [0, 1, 2] as const) {
    const expected = expectedIds.filter((expectedId) => expectedId === id);
    await expect
      .poll(() => api.patch[id].urls.length, {
        message: `PATCHes to host ${id}`
      })
      .toBe(expected.length);
    for (const url of api.patch[id].urls) {
      expect(url.pathname).toMatch(
        new RegExp(`/api/configuration/hosts/${id}$`)
      );
    }
    if (body) {
      for (const sent of api.patch[id].bodies) {
        expect(sent).toEqual(body);
      }
    }
  }
};

test('applies the changes to the hosts ticked one by one, sending only what changed', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await expectHeaderCount(page, 2);

  // Nothing that identifies one host, and no macros yet.
  for (const testId of [
    'host-form-name',
    'host-form-address',
    'host-form-check-options-macros'
  ]) {
    await expect(page.getByTestId(testId)).toHaveCount(0);
  }
  // Incremental, the default: added to each host's own.
  await pickOptions(page, 'host-form-groups', ['Linux servers']);
  await page
    .getByTestId(`host-form-notifications-options-${labelDown}`)
    .click();
  // Replacement with nothing picked clears the categories.
  await page
    .getByTestId('host-form-update-mode-categories-replacement')
    .click();
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();

  await expect(confirmation(page)).toHaveText(
    'The changes will be applied to 2 hosts.'
  );
  await expect(warning(page)).toHaveCount(0);
  await page.getByTestId('confirm').click();

  await expectPatchedHosts(api, [0, 2], {
    category_ids: [],
    host_group_ids_to_add: [1],
    notifications: { options_to_add: ['down'] },
    scheduling_options: { max_check_attempts: 3 }
  });
  await expect(page.getByText('Hosts updated')).toBeVisible();
  await expect(panelHeader(page)).toHaveCount(0);
});

test('applies the changes to every host the check-all selects', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await component.getByLabel('Select all', { exact: true }).click();
  await openMassChange(component);
  // The deactivated host 1 is selected too, although its own checkbox is
  // greyed: known, and out of this ticket.
  await expectHeaderCount(page, 3);
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();

  await expect(confirmation(page)).toHaveText(
    'The changes will be applied to 3 hosts.'
  );
  await page.getByTestId('confirm').click();
  await expectPatchedHosts(api, [0, 1, 2], {
    scheduling_options: { max_check_attempts: 3 }
  });
});

test('writes nothing until the confirmation is accepted', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await tickRow(component, 0);
  await openMassChange(component);
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();
  await page.getByTestId('cancel').click();

  await expectHeaderCount(page, 1);
  await expectPatchedHosts(api, []);
});

test('warns that replacing the templates deletes their services', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();
  // Adding templates deletes nothing.
  await expect(confirmation(page)).toBeVisible();
  await expect(warning(page)).toHaveCount(0);
  await page.getByTestId('cancel').click();

  await page.getByTestId('host-form-update-mode-templates-replacement').click();
  await saveButton(page).click();
  await expect(warning(page)).toHaveAttribute('role', 'alert');
  await expect(warning(page)).toContainText(
    labelTemplateReplacementDeletesServices
  );
  await expectThemedScreenshot(
    confirmation(page),
    'hosts-mass-change-template-warning'
  );

  await page.getByTestId('confirm').click();
  await expectPatchedHosts(api, [0, 2], {
    scheduling_options: { max_check_attempts: 3 },
    template_ids: []
  });
});

test('leaves the additive inheritance alone unless Yes or No is picked', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts(
    { mockApi, mount },
    { isAdditiveInheritanceEnabled: true }
  );
  await tickRow(component, 0);
  await openMassChange(component);

  const inheritance = (value: 'false' | 'true') =>
    page.getByTestId(
      `host-form-notifications-contact-additive-inheritance-${value}`
    );
  await expect(inheritance('true')).toHaveAttribute('aria-pressed', 'false');
  await expect(inheritance('false')).toHaveAttribute('aria-pressed', 'false');
  // Off on purpose, which a switch could not tell from "unchanged".
  await inheritance('false').click();
  await saveButton(page).click();
  await page.getByTestId('confirm').click();

  await expectPatchedHosts(api, [0], {
    notifications: { contact_additive_inheritance: false }
  });
});

test('names the hosts that failed and keeps the panel open on them alone', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts(
    { mockApi, mount },
    { patchFails: [2] }
  );
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();
  await page.getByTestId('confirm').click();

  await expectPatchedHosts(api, [0, 2]);
  await expect(
    page.getByText(`${labelFailedToUpdateSomeResources}: host 2`)
  ).toBeVisible();
  // Still open on what was typed, now aimed at the failed host only.
  await expectHeaderCount(page, 1);
  await expect(
    hostField(page, 'host-form-scheduling-options-maxCheckAttempts')
  ).toHaveValue('3');

  await saveButton(page).click();
  await expect(confirmation(page)).toHaveText(
    'The changes will be applied to 1 host.'
  );
  await page.getByTestId('confirm').click();
  // Host 0 is not written to again.
  await expectPatchedHosts(api, [0, 2, 2]);
});

test('keeps the panel open on every host when they all fail', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts(
    { mockApi, mount },
    { patchFails: [0, 2] }
  );
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await typeMaxCheckAttempts(page, '3');
  await saveButton(page).click();
  await page.getByTestId('confirm').click();

  await expectPatchedHosts(api, [0, 2]);
  await expect(page.getByText('Failed to update the hosts')).toBeAttached();
  await expect(page.getByText('Hosts updated')).toHaveCount(0);
  await expectHeaderCount(page, 2);
  await expect(
    hostField(page, 'host-form-scheduling-options-maxCheckAttempts')
  ).toHaveValue('3');
});

test('asks for the confirmation when the changes are saved on closing', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await page.getByTestId('host-form-update-mode-templates-replacement').click();
  await page.getByLabel(labelClose, { exact: true }).click();
  // The unsaved changes dialog's own Save.
  await page.getByTestId('confirm').click();

  // Not written yet: the count, the hosts and the warning come first.
  await expect(confirmation(page)).toHaveText(
    'The changes will be applied to 2 hosts.'
  );
  await expect(warning(page)).toBeVisible();
  await expectPatchedHosts(api, []);

  await page.getByTestId('confirm').click();
  await expectPatchedHosts(api, [0, 2], { template_ids: [] });
});

test('requires no host group on a cloud platform', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts(
    { mockApi, mount },
    { isCloudPlatform: true }
  );
  await tickRow(component, 0);
  await tickRow(component, 2);
  await openMassChange(component);
  await typeMaxCheckAttempts(page, '3');
  await expect(saveButton(page)).toBeEnabled();
  await saveButton(page).click();
  await page.getByTestId('confirm').click();

  await expectPatchedHosts(api, [0, 2], {
    scheduling_options: { max_check_attempts: 3 }
  });
});
