import { randomUUID } from 'node:crypto';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { expect, test } from '../../fixtures/test';
import type { CentreonApi, ClapiAction } from '../../helpers/CentreonApi';
import { signIn } from '../../helpers/session';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Hosts/10 and Hosts/11: the legacy host and host category listings
 * keep a restricted user within the resources its ACL grants, for what they
 * show and for the actions posted to them, forged requests included.
 */
interface ScopedObject {
  /** API v2 collection used to read the objects back. */
  apiPath: string;
  /** CLAPI actions creating an object called `name`. */
  create: (name: string) => Array<ClapiAction>;
  /** Form field carrying the object id in single actions. */
  idField: string;
  label: string;
  /** Grants the object to the restricted user. */
  scope: (name: string) => {
    hostCategories?: Array<string>;
    hosts?: Array<string>;
  };
  topologyPage: string;
  clapi: string;
}

const scopedObjects: Array<ScopedObject> = [
  {
    apiPath: '/configuration/hosts',
    clapi: 'HOST',
    create: (name) => [
      {
        action: 'ADD',
        object: 'HOST',
        values: `${name};${name};127.0.0.1;generic-host;central;`
      }
    ],
    idField: 'host_id',
    label: 'host',
    scope: (name) => ({ hosts: [name] }),
    topologyPage: '60101'
  },
  {
    apiPath: '/configuration/hosts/categories',
    clapi: 'HC',
    create: (name) => [
      { action: 'ADD', object: 'HC', values: `${name};${name}` }
    ],
    idField: 'hc_id',
    label: 'host category',
    scope: (name) => ({ hostCategories: [name] }),
    topologyPage: '60104'
  }
];

interface ApiObject {
  id: number;
  is_activated: boolean;
  name: string;
}

const find = async (
  api: CentreonApi,
  object: ScopedObject,
  name: string
): Promise<ApiObject | undefined> => {
  const search = encodeURIComponent(JSON.stringify({ name: { $eq: name } }));
  const { result } = await api.v2<{ result: Array<ApiObject> }>(
    'GET',
    `${object.apiPath}?search=${search}`
  );

  return result[0];
};

for (const object of scopedObjects) {
  test.describe(`ACL-scoped ${object.label} listing`, () => {
    let granted: string;
    let denied: string;
    let writer: RestrictedUser;
    let reader: RestrictedUser;
    let ids: { denied: number; granted: number };

    test.beforeEach(async ({ adminApi }) => {
      const suffix = randomUUID().slice(0, 6);
      granted = `pw-acl-granted-${suffix}`;
      denied = `pw-acl-denied-${suffix}`;
      await adminApi.provision([
        ...object.create(granted),
        ...object.create(denied)
      ]);
      writer = restrictedUser({
        menus: [{ access: 'rw', topology: 'Configuration' }],
        name: `pw-acl-rw-${suffix}`,
        ...object.scope(granted)
      });
      reader = restrictedUser({
        menus: [{ access: 'ro', topology: 'Configuration' }],
        name: `pw-acl-ro-${suffix}`,
        ...object.scope(granted)
      });
      await adminApi.provision([
        ...writer.provisioning,
        ...reader.provisioning
      ]);
      ids = {
        denied: (await find(adminApi, object, denied))?.id ?? 0,
        granted: (await find(adminApi, object, granted))?.id ?? 0
      };
    });

    test.afterEach(async ({ adminApi }) => {
      await adminApi.cleanUp([
        ...writer.teardown,
        ...reader.teardown,
        { action: 'DEL', object: object.clapi, values: granted },
        { action: 'DEL', object: object.clapi, values: denied }
      ]);
    });

    const activation = async (api: CentreonApi, name: string) =>
      (await find(api, object, name))?.is_activated;

    test('lists and changes only the granted objects', async ({
      adminApi,
      page
    }) => {
      const listing = new LegacyListingPage(page, object.topologyPage);
      await signIn(page, writer.credentials);

      await listing.open();
      await expect(listing.row(granted)).toBeVisible();
      await expect(listing.row(denied)).toHaveCount(0);

      await test.step('A disable posted for the denied object changes nothing', async () => {
        expect(
          await listing.post({ o: 'u', [object.idField]: String(ids.denied) })
        ).toBe(200);
        expect(await activation(adminApi, denied)).toBe(true);
      });

      await test.step('A bulk disable only changes the granted object', async () => {
        expect(
          await listing.post({
            o: 'mu',
            [`select[${ids.denied}]`]: '1',
            [`select[${ids.granted}]`]: '1'
          })
        ).toBe(200);
        expect(await activation(adminApi, granted)).toBe(false);
        expect(await activation(adminApi, denied)).toBe(true);
      });

      await test.step('A delete posted for the denied object keeps it', async () => {
        expect(
          await listing.post({ o: 'd', [`select[${ids.denied}]`]: '1' })
        ).toBe(200);
        expect(await find(adminApi, object, denied)).toBeDefined();
      });
    });

    if (object.clapi === 'HOST') {
      test('refuses every change to a read-only user', async ({
        adminApi,
        page
      }) => {
        const listing = new LegacyListingPage(page, object.topologyPage);
        await signIn(page, reader.credentials);

        await listing.post({ o: 'mu', [`select[${ids.granted}]`]: '1' });
        expect(await activation(adminApi, granted)).toBe(true);
      });

      test('carries only the granted host into a mass change', async ({
        page
      }) => {
        await signIn(page, writer.credentials);
        await page.goto(
          `/centreon/main.get.php?p=60101&o=mc&select=${ids.granted},${ids.denied}`
        );
        await expect(page.locator('input[name="select"]')).toHaveValue(
          String(ids.granted)
        );
      });
    }

    if (object.clapi === 'HC') {
      test('opens a granted category read-only without script error', async ({
        page
      }) => {
        const errors: Array<string> = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await signIn(page, reader.credentials);
        await page.goto(
          `/centreon/main.get.php?p=60104&o=w&hc_id=${ids.granted}`
        );
        await expect(page.locator('table.formTable')).toBeVisible();
        // The script collapsing the severity rows ran to completion.
        await expect(page.locator('#severity_level')).toBeHidden();
        expect(errors).toEqual([]);
      });
    }
  });
}
