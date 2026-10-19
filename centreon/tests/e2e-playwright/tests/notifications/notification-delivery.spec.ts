import { randomUUID } from 'node:crypto';

import { adminUser } from '../../fixtures/credentials';
import { buildNotification } from '../../fixtures/notifications';
import {
  criticalServiceNames,
  resourcesHostName
} from '../../fixtures/resources';
import { expect, test } from '../../fixtures/test';
import { CentreonApi, type ClapiAction } from '../../helpers/CentreonApi';
import {
  cloudNotificationLog,
  enableNotificationFeature,
  execInWebContainer,
  logCloudNotifications,
  reloadCentralBroker
} from '../../helpers/docker';
import { ensureResourcesMonitored } from '../../helpers/provisioning';

/**
 * Replaces the delivery parts of Cloud-notifications/01: a status change of a
 * service covered by a rule is sent to the contacts of the rule, with its
 * subject. The stream connector logs the emails instead of sending them.
 */
const brokerConfig = 'central-broker-master';
const output = 'central-cloud-notifications-output';
const service = criticalServiceNames[0];

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

const prefix = `pw-delivery-${randomUUID().slice(0, 6)}`;
const hostGroup = `${prefix}-hg`;
const contacts = [1, 2].map((index) => ({
  alias: `${prefix}-contact-${index}`,
  email: `${prefix}-${index}@centreon.test`
}));

const action = (object: string, verb: string, values: string): ClapiAction => ({
  action: verb,
  object,
  values
});

const logLines = (): number =>
  Number(
    execInWebContainer(`cat ${cloudNotificationLog} 2>/dev/null | wc -l`).trim()
  );

const logSince = (line: number): string =>
  execInWebContainer(
    `tail -n +${line + 1} ${cloudNotificationLog} 2>/dev/null || true`
  );

test.beforeAll(async () => {
  test.setTimeout(420_000);
  await ensureResourcesMonitored(baseURL);
  enableNotificationFeature();
  logCloudNotifications();

  const api = await CentreonApi.create(baseURL);
  try {
    await api.authenticate(adminUser);
    const outputs = await api.clapi<Array<{ id: string; name: string }>>(
      action('CENTBROKERCFG', 'LISTOUTPUT', brokerConfig)
    );
    if (!outputs.some(({ name }) => name === output)) {
      await api.provision([
        action('CENTBROKERCFG', 'ADDOUTPUT', `${brokerConfig};${output};lua`)
      ]);
      const created = (
        await api.clapi<Array<{ id: string; name: string }>>(
          action('CENTBROKERCFG', 'LISTOUTPUT', brokerConfig)
        )
      ).find(({ name }) => name === output);
      await api.provision([
        action(
          'CENTBROKERCFG',
          'SETOUTPUT',
          `${brokerConfig};${created?.id};path;/usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`
        ),
        action(
          'CENTBROKERCFG',
          'SETOUTPUT',
          `${brokerConfig};${created?.id};category;neb`
        )
      ]);
    }
    // The host group membership reaches the realtime data with the export.
    await api.provision([
      action('HG', 'ADD', `${hostGroup};${hostGroup}`),
      action('HG', 'ADDMEMBER', `${hostGroup};${resourcesHostName}`),
      ...contacts.map(({ alias, email }) =>
        action(
          'CONTACT',
          'ADD',
          `${alias};${alias};${email};Centreon!2021User;0;1;en_US;local`
        )
      ),
      action('', 'APPLYCFG', 'Central')
    ]);
  } finally {
    await api.dispose();
  }
  reloadCentralBroker();
  // The stream connector creates its log when it starts.
  await expect
    .poll(() => execInWebContainer(`test -f ${cloudNotificationLog} && echo yes || echo no`).trim(), {
      intervals: [5_000],
      timeout: 120_000
    })
    .toBe('yes');
});

test.afterAll(async () => {
  const api = await CentreonApi.create(baseURL);
  try {
    await api.authenticate(adminUser);
    await api.cleanUp([
      action('HG', 'DEL', hostGroup),
      ...contacts.map(({ alias }) => action('CONTACT', 'DEL', alias))
    ]);
  } finally {
    await api.dispose();
  }
});

test('emails the contacts of a rule when a covered service changes status', async ({
  adminApi
}) => {
  test.setTimeout(300_000);
  const { result } = await adminApi.v2<{
    result: Array<{ id: number; name: string }>;
  }>(
    'GET',
    `/configuration/hosts/groups?search=${encodeURIComponent(JSON.stringify({ name: hostGroup }))}`
  );
  const users = await Promise.all(
    contacts.map(({ alias }) => adminApi.findUserId(alias))
  );
  const rule = { ...buildNotification(prefix, result[0].id), users };
  await adminApi.createNotification(rule);

  try {
    // Let the stream connector reload the rules.
    await new Promise((resolve) => setTimeout(resolve, 15_000));
    const start = logLines();
    await adminApi.submitResults([
      { host: resourcesHostName, output: 'back to OK', service, status: 'ok' }
    ]);
    await adminApi.submitResults([
      {
        host: resourcesHostName,
        output: 'failing again',
        service,
        status: 'critical'
      }
    ]);

    await expect
      .poll(() => logSince(start), { intervals: [5_000], timeout: 180_000 })
      .toContain('Notification sent');
    // Each email is logged as its destination and message JSON documents.
    const emails = logSince(start)
      .split('\n')
      .filter((line) => line.includes('Notification sent: '))
      .map((line) => {
        const [destination, message] = line
          .slice(line.indexOf('Notification sent: ') + 19)
          .replace('}{"Subject"', '}\n{"Subject"')
          .split('\n')
          .map((json) => JSON.parse(json));

        return {
          body: message.Body.Html.Data as string,
          recipients: (destination.BccAddresses as Array<string>).sort(),
          subject: message.Subject.Data as string
        };
      });
    expect(emails.length).toBeGreaterThan(0);
    for (const email of emails) {
      expect(email).toEqual({
        body: rule.messages[0].formatted_message,
        recipients: contacts.map(({ email: address }) => address).sort(),
        subject: rule.messages[0].subject
      });
    }
  } finally {
    const { result: rules } = await adminApi.v2<{
      result: Array<{ id: number; name: string }>;
    }>('GET', '/configuration/notifications?limit=100');
    for (const { id } of rules.filter(({ name }) => name === prefix)) {
      await adminApi.v2('DELETE', `/configuration/notifications/${id}`);
    }
  }
});
