import type { Page } from '@playwright/test';

import cards from '../../cypress/fixtures/resources/details/tabs/details/cards.json';
import details from '../../cypress/fixtures/resources/details/tabs/details/details.json';
import detailsByHostType from '../../cypress/fixtures/resources/details/tabs/details/detailsByHostType.json';
import detailsByMetaServiceType from '../../cypress/fixtures/resources/details/tabs/details/detailsByMetaServiceType.json';
import detailsHost from '../../cypress/fixtures/resources/details/tabs/details/detailsHost.json';
import detailsHostWithoutGroupConfiguration from '../../cypress/fixtures/resources/details/tabs/details/detailsHostWithoutGroupConfiguration.json';
import detailsWithAcknowledgment from '../../cypress/fixtures/resources/details/tabs/details/detailsWithAcknowledgment.json';
import detailsWithoutAcknowledgment from '../../cypress/fixtures/resources/details/tabs/details/detailsWithoutAcknowledgment.json';
import detailsWithoutDownTime from '../../cypress/fixtures/resources/details/tabs/details/detailsWithoutDownTime.json';
import timeLine from '../../cypress/fixtures/resources/details/tabs/timeLine/timeLine.json';
import {
  labelAcknowledge,
  labelAcknowledgeCommandSent,
  labelAcknowledgedBy,
  labelAlias,
  labelAt,
  labelCancel,
  labelCategories,
  labelCheck,
  labelCheckDuration,
  labelCommand,
  labelComment,
  labelCurrentNotificationNumber,
  labelCurrentStatusDuration,
  labelDisacknowledge,
  labelDisacknowledgementCommandSent,
  labelDowntime,
  labelDowntimeCommandSent,
  labelDowntimeDuration,
  labelDuration,
  labelFixed,
  labelForcedCheck,
  labelFqdn,
  labelFrom,
  labelGroups,
  labelLastCheck,
  labelLastCheckWithOkStatus,
  labelLastNotification,
  labelLastStatusChange,
  labelLatency,
  labelMore,
  labelNextCheck,
  labelNotify,
  labelNotifyHelpCaption,
  labelPerformanceData,
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription,
  labelSave,
  labelSetDowntime,
  labelStatusChangePercentage,
  labelStatusInformation,
  labelStickyForAnyNonOkStatus,
  labelTimezone,
  labelTo,
  labelYourCommentSent
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

type Mount = (id: string, props?: object) => Promise<unknown>;

// Migrated from Resources/Details/Details.
const storyId = 'Resources/Details/Details/Default';
const resources = './api/latest/monitoring/resources';

interface MountOptions {
  fixture?: unknown;
  panelWidth?: number;
  tab?: 'details' | 'timeline';
}

test.use({ viewport: { height: 800, width: 1280 } });

const mountDetails = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  { fixture = details, panelWidth, tab }: MountOptions = {}
): Promise<void> => {
  await mockApi({
    endpoint: `${resources}/hosts/1/services/1`,
    response: fixture
  });
  await mockApi({ endpoint: 'timeline', response: timeLine });
  await mount(storyId, {
    panelWidth,
    tab
  });
};

const header = (page: Page) => page.getByText('Critical').first();
// Cypress contains(): first element whose text includes the value.
const text = (page: Page, value: string | RegExp) =>
  page.getByText(value).first();

const expectActionsButtons = async (page: Page): Promise<void> => {
  await expect(page.getByTestId('mainAcknowledge')).toBeDisabled();
  await expect(page.getByTestId('mainDisacknowledge')).toBeEnabled();
  await expect(page.getByTestId('mainSetDowntime')).toBeDisabled();
  await expect(page.getByTestId('mainCheck')).toBeEnabled();
};

test('displays resource details information', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount });

  for (const value of [
    'Critical',
    'Centreon',
    labelFqdn,
    'central.centreon.com',
    labelAlias,
    'Central-Centreon',
    labelStatusInformation,
    'OK - 127.0.0.1 rta 0.100ms lost 0%',
    'OK - 127.0.0.1 rta 0.99ms lost 0%',
    'OK - 127.0.0.1 rta 0.98ms lost 0%'
  ]) {
    await expect(text(page, value)).toBeVisible();
  }
  await expect(page.getByText('OK - 127.0.0.1 rta 0.97ms lost 0%')).toHaveCount(
    0
  );
  await text(page, labelMore).click();
  await expect(text(page, 'OK - 127.0.0.1 rta 0.97ms lost 0%')).toBeVisible();

  await expect(page.getByText(labelComment, { exact: true })).toHaveCount(3);
  await expect(
    page.getByText(labelDowntimeDuration, { exact: true })
  ).toHaveCount(2);
  for (const value of [
    `${labelFrom} 01/18/2020 6:57 PM`,
    `${labelTo} 01/18/2020 7:57 PM`,
    `${labelFrom} 02/18/2020 6:57 PM`,
    `${labelTo} 02/18/2020 7:57 PM`,
    'First downtime set by Admin',
    'Second downtime set by Admin',
    labelAcknowledgedBy,
    `Admin ${labelAt} 03/18/2020 7:57 PM`,
    'Acknowledged by Admin',
    labelTimezone,
    'Europe/Paris',
    labelCurrentStatusDuration,
    '22m - 3/3 (Hard)',
    labelLastStatusChange,
    '04/18/2020 5:00 PM',
    labelLastCheck,
    '05/18/2020 6:00 PM'
  ]) {
    await expect(text(page, value)).toBeVisible();
  }
  for (const value of [
    labelNextCheck,
    '06/18/2020 7:15 PM',
    labelCheckDuration,
    '0.070906 s',
    labelLastCheckWithOkStatus,
    labelLatency,
    '0.005 s',
    labelCheck,
    labelStatusChangePercentage,
    '3.5%',
    labelLastNotification,
    '07/18/2020 7:30 PM',
    labelCurrentNotificationNumber,
    labelGroups,
    'Linux-servers',
    labelCategories,
    'Windows',
    labelPerformanceData,
    'rta=0.025ms;200.000;400.000;0; rtmax=0.061ms;;;; rtmin=0.015ms;;;; pl=0%;20;50;0;100',
    labelCommand,
    /^base_host_alive$/,
    '--test',
    '3000,80',
    '5000,100',
    /^--test2="ok"$/
  ]) {
    await expect(text(page, value)).toBeAttached();
  }
  await expect(page.getByTestId('FlappingIcon')).toBeAttached();
  await expectThemedScreenshot(page, 'resources-details-information');
});

test('displays actions as icons when the panel width is less than 615 px', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount });
  await expect(header(page)).toBeVisible();
  await expectActionsButtons(page);
  await expectThemedScreenshot(page, 'resources-details-actions-icons');
});

test('displays actions as buttons when panel width exceeds 615 px', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount }, { panelWidth: 800 });
  await expect(header(page)).toBeVisible();
  await expectActionsButtons(page);
  await expectThemedScreenshot(page, 'resources-details-actions-buttons');
});

const expectSent = async (
  mock: MockedEndpoint,
  message: string,
  page: Page
) => {
  await expect.poll(() => mock.bodies.length).toBe(1);
  await expect(text(page, message)).toBeVisible();
};

test('sends the acknowledgment action from the acknowledgment modal', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  const acknowledge = await mockApi({
    endpoint: `${resources}/acknowledge`,
    method: 'POST',
    status: 204
  });
  await mountDetails(
    { mockApi, mount },
    { fixture: detailsWithoutAcknowledgment }
  );
  await expect(header(page)).toBeVisible();
  await page.getByTestId('mainAcknowledge').click();
  await expect(page.getByTestId('mainDisacknowledge')).toBeDisabled();
  const dialog = page.getByTestId('dialogAcknowledge');
  await expect(dialog).toBeVisible();
  for (const value of [
    labelCancel,
    labelAcknowledge,
    labelComment,
    labelNotify,
    labelNotifyHelpCaption,
    labelStickyForAnyNonOkStatus
  ]) {
    await expect(dialog.getByText(value).first()).toBeVisible();
  }
  await expectThemedScreenshot(page, 'resources-details-acknowledge-modal');

  await page.getByTestId('Confirm').click();
  await expectSent(acknowledge, labelAcknowledgeCommandSent, page);
});

test('sends the disacknowledgment action from the disacknowledgment modal', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  const disacknowledge = await mockApi({
    endpoint: `${resources}/acknowledgements`,
    method: 'DELETE',
    status: 204
  });
  await mountDetails(
    { mockApi, mount },
    { fixture: detailsWithAcknowledgment }
  );
  await expect(header(page)).toBeVisible();
  await page.getByTestId('mainDisacknowledge').click();
  await expect(page.getByTestId('mainAcknowledge')).toBeDisabled();
  const modal = page.getByTestId('modalDisacknowledge');
  await expect(modal).toBeVisible();
  await expect(modal.getByText(labelDisacknowledge).first()).toBeVisible();
  await expect(modal.getByText(labelCancel)).toBeVisible();
  await expectThemedScreenshot(page, 'resources-details-disacknowledge-modal');

  await page.getByTestId('Confirm').click();
  await expectSent(disacknowledge, labelDisacknowledgementCommandSent, page);
});

test('sends the downtime action from the downtime modal', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await page.clock.setFixedTime(new Date('2023-02-14T10:55:00Z'));
  const downtime = await mockApi({
    endpoint: `${resources}/downtime`,
    method: 'POST',
    status: 204
  });
  await mountDetails({ mockApi, mount }, { fixture: detailsWithoutDownTime });
  await expect(header(page)).toBeVisible();
  await page.getByTestId('mainSetDowntime').click();
  const dialog = page.getByTestId('dialogDowntime');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText(labelDowntime).first()).toBeVisible();
  // One hour by default, shown in the user timezone (Europe/Paris).
  await expect(
    page.locator('input[value="02/14/2023 11:55 AM"]')
  ).toBeVisible();
  await expect(
    page.locator('input[value="02/14/2023 12:55 PM"]')
  ).toBeVisible();
  for (const value of [
    labelTo,
    labelDuration,
    labelFixed,
    labelComment,
    labelCancel,
    labelSetDowntime
  ]) {
    await expect(dialog.getByText(value).first()).toBeVisible();
  }
  await expectThemedScreenshot(page, 'resources-details-downtime-modal');

  await page.getByTestId('Confirm').click();
  await expectSent(downtime, labelDowntimeCommandSent, page);
});

test('sends the forced check then the check command chosen from the menu', async ({
  mockApi,
  mount,
  page
}) => {
  const check = await mockApi({
    endpoint: `${resources}/check`,
    method: 'POST',
    status: 204
  });
  await mountDetails({ mockApi, mount });
  await expect(header(page)).toBeVisible();
  await expect(page.getByTestId('mainCheck')).toBeEnabled();

  const list = page.getByRole('tooltip');
  await page.getByTestId('arrow').click();
  for (const value of [
    labelCheck,
    labelResourceDetailsCheckDescription,
    labelForcedCheck,
    labelResourceDetailsForcedCheckDescription
  ]) {
    await expect(list.getByText(value).first()).toBeVisible();
  }
  await list.getByText(labelForcedCheck, { exact: true }).click();
  await page.getByTestId('arrow').click();
  await expect(list).toHaveCount(0);
  await page.getByTestId('mainCheck').click();
  await expectSent(check, labelResourceDetailsForcedCheckCommandSent, page);

  await page.getByTestId('arrow').click();
  await list.getByText(labelCheck, { exact: true }).click();
  await page.getByTestId('arrow').click();
  await expect(list).toHaveCount(0);
  await page.getByTestId('mainCheck').click();
  await expect.poll(() => check.bodies.length).toBe(2);
  await expect(text(page, labelResourceDetailsCheckCommandSent)).toBeVisible();
});

test('displays the comment area when the corresponding button is clicked', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount }, { tab: 'timeline' });
  await expect(header(page)).toBeVisible();
  await page.getByTestId('addComment').click();
  await expect(page.getByTestId('commentArea')).toBeVisible();
  await expect(page.getByTestId(labelCancel)).toBeEnabled();
  await expect(page.getByTestId(labelSave)).toBeDisabled();
  await expectThemedScreenshot(page, 'resources-details-comment-area');
});

for (const { fixture, resourceType } of [
  { fixture: detailsByHostType, resourceType: 'host' },
  { fixture: details, resourceType: 'service' },
  { fixture: detailsByMetaServiceType, resourceType: 'meta-service' }
]) {
  test(`submits a comment for a resource of type ${resourceType}`, async ({
    mockApi,
    mount,
    page
  }) => {
    const comment = await mockApi({
      endpoint: `${resources}/comments`,
      method: 'POST',
      status: 204
    });
    await mountDetails({ mockApi, mount }, { fixture, tab: 'timeline' });
    await expect(header(page)).toBeVisible();
    await page.getByTestId('addComment').click();
    await page.getByTestId('commentArea').fill('comment from centreon web');
    await expect(page.getByTestId(labelCancel)).toBeEnabled();
    await page.getByTestId(labelSave).click();
    await expectSent(comment, labelYourCommentSent, page);
  });
}

test('hides the comment area when the cancel button is clicked', async ({
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount }, { tab: 'timeline' });
  await page.getByTestId('addComment').click();
  await expect(page.getByTestId('commentArea')).toBeAttached();
  await expect(page.getByTestId(labelSave)).toBeDisabled();
  await page.getByTestId(labelCancel).click();
  await expect(page.getByTestId('commentArea')).toHaveCount(0);
});

test('masks the date cards whose returned value is 0', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount }, { fixture: cards });
  await expect(text(page, 'Downtime duration')).toBeVisible();
  for (const label of [
    labelLastStatusChange,
    labelLastCheck,
    labelLastCheckWithOkStatus,
    labelNextCheck,
    labelLastNotification
  ]) {
    await expect(page.getByText(label, { exact: true })).toHaveCount(0);
  }
  await expectThemedScreenshot(page, 'resources-details-masked-cards');
});

test('redirects to the host group configuration when the group chip is clicked', async ({
  mockApi,
  mount,
  page
}) => {
  await mountDetails({ mockApi, mount }, { fixture: detailsHost });
  await text(page, 'Linux-servers').hover();
  await page.getByLabel('Linux-servers Configure').click();
  await expect(page.getByTestId('location')).toHaveValue(
    '/configuration/hosts/groups?mode=edit&id=0'
  );
});

test('hides the group configuration icon when its endpoint is not available', async ({
  mockApi,
  mount,
  page
}) => {
  await mountDetails(
    { mockApi, mount },
    { fixture: detailsHostWithoutGroupConfiguration }
  );
  await text(page, 'Linux-servers').hover();
  await expect(text(page, 'Linux-servers')).toBeVisible();
  await expect(page.getByLabel('Linux-servers Configure')).toHaveCount(0);
});
