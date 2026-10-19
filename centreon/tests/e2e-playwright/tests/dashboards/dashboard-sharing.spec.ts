import { randomUUID } from 'node:crypto';

import type { Browser, Page } from '@playwright/test';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';
import { DashboardSharingDialog } from '../../pages/DashboardSharingDialog';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

/**
 * Replaces Dashboards/06 and 07: what sharing a dashboard grants, checked
 * from the session of the user it is shared with rather than from the list of
 * the sharing dialog alone.
 */
const dashboard = (name: string) => ({
  description: null,
  name,
  panels: [],
  refresh: { interval: null, type: 'global' }
});

const creator = (name: string): RestrictedUser =>
  restrictedUser({
    contactParams: { reach_api: '1' },
    menus: [{ access: 'rw', topology: 'Home;Dashboards;Creator' }],
    name
  });

/** What a user sees of a dashboard: absent, read-only or editable. */
const accessOf = async (
  browser: Browser,
  user: RestrictedUser,
  name: string
): Promise<'editable' | 'hidden' | 'read-only'> => {
  const context = await browser.newContext();
  const page: Page = await context.newPage();
  try {
    await signIn(page, user.credentials);
    const library = new DashboardsListPage(page);
    await library.open();
    // The listing renders after its request: give the card time to show.
    const listed = await page
      .getByRole('heading', { exact: true, name })
      .waitFor({ timeout: 5_000 })
      .then(() => true)
      .catch(() => false);
    if (!listed) {
      return 'hidden';
    }
    await library.openDashboard(name);
    const detail = new DashboardDetailPage(page);
    await detail.expectTitle(name);

    return (await detail.editButton.count()) > 0 ? 'editable' : 'read-only';
  } finally {
    await context.close();
  }
};

test.describe('Dashboard sharing', () => {
  let name: string;
  let id: number;
  const users: Array<RestrictedUser> = [];
  let group: string | undefined;

  test.beforeEach(async ({ adminApi, page }) => {
    name = `pw-share-${randomUUID().slice(0, 6)}`;
    id = await adminApi.createDashboard(dashboard(name));
    await signIn(page, adminUser);
  });

  test.afterEach(async ({ adminApi }) => {
    await adminApi
      .v2('DELETE', `/configuration/dashboards/${id}`)
      .catch(() => undefined);
    await adminApi.cleanUp([
      ...users.splice(0).flatMap((user) => user.teardown),
      ...(group ? [{ action: 'DEL', object: 'CG', values: group }] : [])
    ]);
    group = undefined;
  });

  test('grants, changes and revokes the rights of a user', async ({
    adminApi,
    browser,
    page
  }) => {
    const user = creator(`${name}-user`);
    users.push(user);
    await adminApi.provision(user.provisioning);
    const library = new DashboardsListPage(page);
    const sharing = new DashboardSharingDialog(page);
    const login = user.credentials.login;

    await library.open();
    expect(await accessOf(browser, user, name)).toBe('hidden');

    await sharing.open(name);
    await sharing.add(login, 'Viewer');
    await sharing.save();
    expect(await accessOf(browser, user, name)).toBe('read-only');

    await sharing.open(name);
    await sharing.setRole(login, 'Editor');
    await sharing.save();
    expect(await accessOf(browser, user, name)).toBe('editable');

    await sharing.open(name);
    await sharing.remove(login);
    await sharing.save();
    expect(await accessOf(browser, user, name)).toBe('hidden');
  });

  test('grants the rights of a contact group to its members', async ({
    adminApi,
    browser,
    page
  }) => {
    const member = creator(`${name}-member`);
    const outsider = creator(`${name}-outsider`);
    users.push(member, outsider);
    group = `${name}-group`;
    await adminApi.provision([
      ...member.provisioning,
      ...outsider.provisioning,
      { action: 'ADD', object: 'CG', values: `${group};${group}` },
      {
        action: 'ADDCONTACT',
        object: 'CG',
        values: `${group};${member.credentials.login}`
      },
      // Only the groups of an access group with dashboard rights are offered.
      {
        action: 'ADDCONTACTGROUP',
        object: 'ACLGROUP',
        values: `${member.credentials.login}-group;${group}`
      },
      { action: 'RELOAD', object: 'ACL', values: '' }
    ]);
    const library = new DashboardsListPage(page);
    const sharing = new DashboardSharingDialog(page);

    await library.open();
    await sharing.open(name);
    await sharing.add(group, 'Editor', { group: true });
    await sharing.save();

    expect(await accessOf(browser, member, name)).toBe('editable');
    expect(await accessOf(browser, outsider, name)).toBe('hidden');
  });
});
