import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import type { CentreonApi, ClapiAction } from '../../helpers/CentreonApi';
import { enableNotificationFeature, ensureStack } from '../../helpers/docker';
import { NotificationsListPage } from '../../pages/NotificationsListPage';

/**
 * Replaces the configuration parts of Cloud-notifications/01 to 03: a rule
 * created from the form is saved with its resources, contacts and message,
 * its duplicate keeps them, and deleting it removes it. Delivering the
 * emails needs the broker Lua output of the notification module; postponed.
 */
test.use({ storageState: adminStorageStatePath });

interface RuleDetails {
  id: number;
  messages: Array<{ subject: string }>;
  name: string;
  resources: Array<{
    events: number;
    extra?: { event_services: number };
    ids: Array<{ name: string }>;
    type: string;
  }>;
  timeperiod: { name: string };
  users: Array<{ alias: string }>;
}

const ruleNamed = async (
  adminApi: CentreonApi,
  name: string
): Promise<RuleDetails | undefined> => {
  const { result } = await adminApi.v2<{
    result: Array<{ id: number; name: string }>;
  }>(
    'GET',
    `/configuration/notifications?search=${encodeURIComponent(JSON.stringify({ name }))}`
  );
  const rule = result.find((entry) => entry.name === name);

  return rule
    ? adminApi.v2<RuleDetails>('GET', `/configuration/notifications/${rule.id}`)
    : undefined;
};

/** What a rule notifies, independently of its id and name. */
const settings = ({ messages, resources, timeperiod, users }: RuleDetails) => ({
  // The form also sends an empty service group resource.
  resources: resources
    .filter(({ ids }) => ids.length > 0)
    .map(({ extra, ids, type }) => ({
    names: ids.map(({ name }) => name),
    serviceEvents: extra?.event_services,
    type
  })),
  subject: messages[0]?.subject,
  timePeriod: timeperiod.name,
  users: users.map(({ alias }) => alias).sort()
});

let prefix: string;
let teardown: Array<ClapiAction>;
let rules: Array<string>;

test.beforeAll(async () => {
  await ensureStack({ services: ['web'] });
  enableNotificationFeature();
});

test.beforeEach(async ({ adminApi }) => {
  prefix = `pw-notif-${randomUUID().slice(0, 6)}`;
  rules = [];
  teardown = [
    { action: 'DEL', object: 'HG', values: `${prefix}-hg` },
    { action: 'DEL', object: 'CONTACT', values: `${prefix}-contact-1` },
    { action: 'DEL', object: 'CONTACT', values: `${prefix}-contact-2` }
  ];
  await adminApi.provision([
    { action: 'ADD', object: 'HG', values: `${prefix}-hg;${prefix}-hg` },
    ...[1, 2].map((index) => ({
      action: 'ADD',
      object: 'CONTACT',
      values: `${prefix}-contact-${index};${prefix}-contact-${index};${prefix}-${index}@centreon.test;Centreon!2021User;0;1;en_US;local`
    }))
  ]);
});

test.afterEach(async ({ adminApi }) => {
  for (const name of rules) {
    const rule = await ruleNamed(adminApi, name).catch(() => undefined);
    if (rule) {
      await adminApi.v2('DELETE', `/configuration/notifications/${rule.id}`);
    }
  }
  await adminApi.cleanUp(teardown);
});

test('creates, duplicates and deletes a notification rule', async ({
  adminApi,
  page
}) => {
  const name = `${prefix}-rule`;
  const copy = `${prefix}-copy`;
  rules.push(name, copy);
  const notifications = new NotificationsListPage(page);
  await notifications.open();

  await notifications.create({
    body: 'Body of the notification',
    contacts: [`${prefix}-contact-1`, `${prefix}-contact-2`],
    hostGroup: `${prefix}-hg`,
    name,
    subject: 'Subject of the notification',
    timePeriod: '24x7'
  });
  const created = await ruleNamed(adminApi, name);
  expect(created && settings(created)).toEqual({
    // The host group and every service event of its hosts.
    resources: [
      { names: [`${prefix}-hg`], serviceEvents: 15, type: 'hostgroup' }
    ],
    subject: 'Subject of the notification',
    timePeriod: '24x7',
    users: [`${prefix}-contact-1`, `${prefix}-contact-2`]
  });

  await notifications.duplicate(name, copy);
  const duplicated = await ruleNamed(adminApi, copy);
  expect(duplicated && settings(duplicated)).toEqual(
    created && settings(created)
  );

  await notifications.remove(name);
  expect(await ruleNamed(adminApi, name)).toBeUndefined();
  await expect(notifications.row(copy)).toBeVisible();
});
