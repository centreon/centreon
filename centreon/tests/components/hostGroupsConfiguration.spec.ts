import type { Locator, Page } from '@playwright/test';

import {
  labelAdditionalInformation,
  labelAlias,
  labelApplyResourceAccessRule,
  labelComments,
  labelDisabledHosts,
  labelEnabledHosts,
  labelGeneralInformation,
  labelGeographicCoordinates,
  labelGroupMembers,
  labelIcon,
  labelInvalidCoordinateFormat,
  labelName,
  labelNoEnabledHosts,
  labelResourceAccessRule,
  labelSelectHosts
} from '../../www/front_src/src/Configuration/HostGroups/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Configuration/HostGroups/Specs (Listing, Filters and Modal suites).
const storyId = 'HostGroups/HostGroups/Default';

type Platform = 'cloud' | 'onPrem';
interface HostGroupsData {
  details: Record<Platform, unknown>;
  hostGroups: unknown;
  hosts: unknown;
  hostsEmpty: unknown;
  images: unknown;
  payload: Record<Platform, Record<string, unknown>>;
  rules: unknown;
}

let data: HostGroupsData;
let listing: { urls: Array<URL> };
let creation: { bodies: Array<unknown> };
let update: { bodies: Array<unknown> };

test.use({ viewport: { height: 900, width: 1440 } });

test.beforeEach(async ({ loadStoryData, mockApi }) => {
  data = await loadStoryData<HostGroupsData>(
    'HostGroups/HostGroups/Data',
    'hostGroupsData'
  );
  listing = await mockApi({
    endpoint: 'configuration/hosts/groups',
    response: data.hostGroups
  });
  creation = await mockApi({
    endpoint: 'configuration/hosts/groups',
    method: 'POST',
    response: data.details.onPrem
  });
  update = await mockApi({
    endpoint: 'configuration/hosts/groups/1',
    method: 'PUT',
    response: {}
  });
  await mockApi({ endpoint: 'configuration/hosts', response: data.hosts });
  await mockApi({
    endpoint: 'administration/resource-access/rules',
    response: data.rules
  });
  await mockApi({ endpoint: 'configuration/icons', response: data.images });
});

const mockDetails = (
  mockApi: (options: { endpoint: string; response: unknown }) => unknown,
  platform: Platform
) =>
  mockApi({
    endpoint: 'configuration/hosts/groups/1',
    response: data.details[platform]
  });

const mountHostGroups = async (
  mount: (id: string, props?: object) => Promise<Locator>,
  props = {}
) => {
  const component = await mount(storyId, props);
  await expect(
    component.getByText('host group 0', { exact: true })
  ).toBeVisible();

  return component;
};

const row = (component: Locator, name: string) =>
  component
    .getByRole('row')
    .filter({ has: component.page().getByText(name, { exact: true }) });

test.describe('listing', () => {
  test('renders the page with the configuration layout', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mountHostGroups(mount);

    await expect(component.getByText('Host groups').first()).toBeVisible();
    await expectThemedScreenshot(component, 'host-groups-listing');
  });

  test('hides the actions without write access', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mountHostGroups(mount, { hasWriteAccess: false });

    await expect(component.getByTestId('add-resource')).toHaveCount(0);
    await expectThemedScreenshot(component, 'host-groups-listing-read-only');
  });

  for (const [label, column] of [
    [labelName, 'name'],
    [labelAlias, 'alias']
  ]) {
    test(`sorts by ${column}`, async ({ mount }) => {
      const component = await mountHostGroups(mount);
      await component.getByLabel(`Column ${label}`).click();

      await expect
        .poll(() =>
          JSON.parse(listing.urls.at(-1)?.searchParams.get('sort_by') ?? '{}')
        )
        .toEqual({ [column]: 'desc' });
    });
  }

  test('truncates names and aliases longer than 50 characters', async ({
    mount
  }) => {
    const component = await mountHostGroups(mount);

    await expect(
      component.getByText(`${'hostGroup0'.repeat(5)}...`)
    ).toBeVisible();
    await expect(component.getByText(`${'alias'.repeat(10)}...`)).toBeVisible();
  });

  test.describe('host counts', () => {
    for (const [name, count, title] of [
      ['host group 4', '12', labelEnabledHosts],
      ['host group 3', '15', labelDisabledHosts]
    ]) {
      test(`lists the hosts behind ${title.toLowerCase()}`, async ({
        mount,
        page
      }) => {
        const component = await mountHostGroups(mount);
        await row(component, name).getByText(count, { exact: true }).hover();

        await expect(page.getByRole('tooltip').getByText(title)).toBeVisible();
        await expect(
          page.getByRole('tooltip').getByText('host 1', { exact: true })
        ).toBeVisible();
      });
    }

    // Host group 1 has no enabled host, host group 2 no disabled one.
    for (const [name, title] of [
      ['host group 1', labelNoEnabledHosts],
      ['host group 2', labelDisabledHosts]
    ]) {
      test(`shows "${title}" for an empty count`, async ({
        mockApi,
        mount,
        page
      }) => {
        await mockApi({
          endpoint: 'configuration/hosts',
          response: data.hostsEmpty
        });
        const component = await mountHostGroups(mount);
        await row(component, name).getByText('0', { exact: true }).hover();

        await expect(page.getByRole('tooltip').getByText(title)).toBeVisible();
      });
    }
  });
});

test('opens the advanced filters', async ({ mount, page }) => {
  const component = await mountHostGroups(mount);
  await expect(
    component.locator('div[data-testid="search-bar"]')
  ).toBeVisible();
  await component.getByTestId('Filters').click();

  const filters = page.getByTestId('advanced-filters');
  await expect(filters).toBeVisible();
  await expect(filters.getByText(labelName).first()).toBeVisible();
  await expect(filters.getByText(labelAlias).first()).toBeVisible();
});

test.describe('modal', () => {
  const modal = (page: Page) => page.getByRole('dialog');
  // Fields carry their test id on both the wrapper and the input.
  const field = (page: Page, testId: string) =>
    modal(page).locator(`:is(input, textarea)[data-testid="${testId}"]`);
  const submit = (page: Page) => modal(page).getByTestId('submit');
  const groups = (isCloud: boolean) => [
    labelGeneralInformation,
    labelGroupMembers,
    ...(isCloud ? [labelResourceAccessRule] : []),
    labelAdditionalInformation
  ];
  const pick = async (page: Page, testId: string, options: Array<string>) => {
    await modal(page).getByTestId(testId).click();
    for (const name of options) {
      await page.getByRole('option', { exact: true, name }).click();
    }
    await modal(page).getByTestId('Modal-header').click();
  };

  for (const platform of ['onPrem', 'cloud'] as const) {
    const isCloudPlatform = platform === 'cloud';

    test.describe(platform, () => {
      test.beforeEach(({ mockApi }) => mockDetails(mockApi, platform));

      test('opens in view mode without write access', async ({
        expectThemedScreenshot,
        mount,
        page
      }) => {
        const component = await mountHostGroups(mount, {
          hasWriteAccess: false,
          isCloudPlatform
        });
        await component.getByText('host group 1', { exact: true }).click();

        await expect(modal(page).getByText('View a host group')).toBeVisible();
        await expect(field(page, labelName)).toHaveValue('host group 1 name');
        await expectThemedScreenshot(
          modal(page),
          `host-group-view-${platform}`
        );
      });

      test('opens an empty creation form', async ({ mount, page }) => {
        const component = await mountHostGroups(mount, { isCloudPlatform });
        await component.getByTestId('add-resource').click();

        for (const group of groups(isCloudPlatform)) {
          await expect(modal(page).getByText(group).first()).toBeVisible();
        }
        for (const testId of [
          labelName,
          labelAlias,
          labelSelectHosts,
          labelGeographicCoordinates,
          labelComments
        ]) {
          await expect(field(page, testId)).toHaveValue('');
        }
        await expect(field(page, labelApplyResourceAccessRule)).toHaveCount(
          isCloudPlatform ? 1 : 0
        );
      });

      test('opens the edition form with the API values', async ({
        expectThemedScreenshot,
        mount,
        page
      }) => {
        const component = await mountHostGroups(mount, { isCloudPlatform });
        await component.getByText('host group 1', { exact: true }).click();

        const payload = data.payload.onPrem;
        await expect(field(page, labelName)).toHaveValue(String(payload.name));
        await expect(field(page, labelAlias)).toHaveValue(
          String(payload.alias)
        );
        for (const host of ['host 1', 'host 2', 'host 3']) {
          await expect(
            modal(page).getByText(host, { exact: true })
          ).toBeVisible();
        }
        if (isCloudPlatform) {
          for (const rule of ['rule 1', 'rule 2']) {
            await expect(
              modal(page).getByText(rule, { exact: true })
            ).toBeVisible();
          }
        } else {
          await expect(field(page, labelApplyResourceAccessRule)).toHaveCount(
            0
          );
        }
        await expect(field(page, labelGeographicCoordinates)).toHaveValue(
          String(payload.geo_coords)
        );
        await expect(field(page, labelComments)).toHaveValue(
          String(payload.comment)
        );
        await expectThemedScreenshot(
          modal(page),
          `host-group-edition-${platform}`
        );
      });

      test('creates a host group', async ({ mount, page }) => {
        const component = await mountHostGroups(mount, { isCloudPlatform });
        await component.getByTestId('add-resource').click();

        const payload = data.payload[platform];
        await field(page, labelName).fill(String(payload.name));
        await field(page, labelAlias).fill(String(payload.alias));
        await field(page, labelComments).fill(String(payload.comment));
        await field(page, labelGeographicCoordinates).fill(
          String(payload.geo_coords)
        );
        await pick(page, labelSelectHosts, ['host 1', 'host 2', 'host 3']);
        if (isCloudPlatform) {
          await pick(page, labelApplyResourceAccessRule, ['rule 1', 'rule 2']);
        }
        // Icon options are named after both their image and their label.
        await pick(page, labelIcon, ['cypress_logo cypress_logo']);
        await submit(page).click();

        await expect.poll(() => creation.bodies).toEqual([payload]);
      });

      test('updates a host group', async ({ mount, page }) => {
        const component = await mountHostGroups(mount, { isCloudPlatform });
        await component.getByText('host group 1', { exact: true }).click();
        await expect(field(page, labelName)).toHaveValue('host group 1 name');

        await field(page, labelName).fill('Updated name');
        await submit(page).click();

        await expect
          .poll(() => update.bodies)
          .toEqual([{ ...data.payload[platform], name: 'Updated name' }]);
        await expect(page.getByText('Host group updated')).toBeVisible();
      });
    });
  }

  test('validates the geographic coordinates', async ({ mount, page }) => {
    const component = await mountHostGroups(mount);
    await component.getByTestId('add-resource').click();
    await field(page, labelName).fill('name');

    const coordinates = field(page, labelGeographicCoordinates);
    await coordinates.fill('123');
    await modal(page).getByTestId('Modal-header').click();
    await expect(
      modal(page).getByText(labelInvalidCoordinateFormat)
    ).toBeVisible();
    await expect(submit(page)).toBeDisabled();

    await coordinates.fill('-40.12,22.44');
    await expect(
      modal(page).getByText(labelInvalidCoordinateFormat)
    ).toHaveCount(0);
    await expect(submit(page)).toBeEnabled();
  });

  test('requires a resource access rule on cloud', async ({ mount, page }) => {
    const component = await mountHostGroups(mount, { isCloudPlatform: true });
    await component.getByTestId('add-resource').click();
    await field(page, labelName).fill('name');
    await expect(submit(page)).toBeDisabled();

    await modal(page).getByTestId(labelApplyResourceAccessRule).click();
    await page.getByRole('option', { exact: true, name: 'rule 1' }).click();
    await expect(submit(page)).toBeEnabled();
  });
});
