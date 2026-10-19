import { randomUUID } from 'node:crypto';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

/**
 * Replaces Dashboards/05: what each dashboard role can see and do, one test
 * per role instead of three scenarios. A dashboard shared with the user and a
 * private one tell the library scope apart.
 */
interface Role {
  canCreate: boolean;
  canEdit: boolean;
  /** Dashboards ACL menu granting the role, none for the platform admin. */
  menu?: string;
  name: string;
  /** Role the shared dashboard grants the user. */
  shareRole: 'editor' | 'viewer';
  seesPrivate: boolean;
}

const dashboard = (name: string) => ({
  description: null,
  name,
  panels: [],
  refresh: { interval: null, type: 'global' }
});

const roles: Array<Role> = [
  {
    canCreate: true,
    canEdit: true,
    name: 'platform administrator',
    seesPrivate: true,
    shareRole: 'editor'
  },
  {
    canCreate: true,
    canEdit: true,
    menu: 'Administrator',
    name: 'dashboard administrator',
    seesPrivate: true,
    shareRole: 'viewer'
  },
  {
    canCreate: true,
    canEdit: true,
    menu: 'Creator',
    name: 'dashboard editor',
    seesPrivate: false,
    shareRole: 'editor'
  },
  {
    canCreate: false,
    canEdit: false,
    menu: 'Viewer',
    name: 'dashboard viewer',
    seesPrivate: false,
    shareRole: 'viewer'
  }
];

for (const role of roles) {
  test(`applies the ${role.name} permissions`, async ({ adminApi, page }) => {
    const suffix = randomUUID().slice(0, 6);
    const shared = `pw-shared-${suffix}`;
    const privateDashboard = `pw-private-${suffix}`;
    const created = `pw-created-${suffix}`;
    let user: RestrictedUser | undefined;
    const ids: Array<number> = [];

    try {
      ids.push(
        await adminApi.createDashboard(dashboard(shared)),
        await adminApi.createDashboard(dashboard(privateDashboard))
      );

      if (role.menu) {
        user = restrictedUser({
          contactParams: { reach_api: '1' },
          menus: [{ access: 'rw', topology: `Home;Dashboards;${role.menu}` }],
          name: `pw-dash-${suffix}`
        });
        await adminApi.provision(user.provisioning);
        const contactId = await adminApi.findUserId(user.credentials.login);
        await adminApi.v2('PUT', `/configuration/dashboards/${ids[0]}/shares`, {
          contact_groups: [],
          contacts: [{ id: contactId, role: role.shareRole }]
        });
      }
      await signIn(page, user?.credentials ?? adminUser);

      const library = new DashboardsListPage(page);
      const detail = new DashboardDetailPage(page);
      await library.open();

      await test.step('The library lists the dashboards of the role', async () => {
        await library.expectVisible(shared);
        if (role.seesPrivate) {
          await library.expectVisible(privateDashboard);
        } else {
          await library.expectNotVisible(privateDashboard);
        }
      });

      await test.step('The shared dashboard opens with the role rights', async () => {
        await library.openDashboard(shared);
        await detail.expectOnDetailPage();
        await expect(detail.editButton).toHaveCount(role.canEdit ? 1 : 0);
      });

      await test.step('Creating and deleting a dashboard', async () => {
        await library.open();
        if (!role.canCreate) {
          await expect(library.createButton).toHaveCount(0);
          await expect(
            library.card(shared).getByRole('button', { name: 'More actions' })
          ).toHaveCount(0);
          return;
        }
        await library.clickCreate();
        await page.getByLabel('Name', { exact: true }).fill(created);
        await page.getByRole('button', { name: 'Create' }).click();
        await detail.expectOnDetailPage();
        await library.open();
        await library.openDelete(created);
        await page
          .getByRole('dialog')
          .getByRole('button', { name: 'Delete' })
          .click();
        await library.expectNotVisible(created);
      });
    } finally {
      for (const id of ids) {
        await adminApi
          .v2('DELETE', `/configuration/dashboards/${id}`)
          .catch(() => undefined);
      }
      if (user) {
        await adminApi.cleanUp(user.teardown);
      }
    }
  });
}
