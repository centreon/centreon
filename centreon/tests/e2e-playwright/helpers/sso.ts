import { randomUUID } from 'node:crypto';

import { type Browser, expect, type Page } from '@playwright/test';

import { type RestrictedUser, restrictedUser } from '../fixtures/acl';
import type { Credentials } from '../fixtures/credentials';
import { KeycloakLoginPage } from '../pages/KeycloakLoginPage';
import { LoginPage } from '../pages/LoginPage';
import { MainHeader } from '../pages/MainHeader';
import type { CentreonApi, ClapiAction } from './CentreonApi';
import { queryCentreonDb } from './docker';

/** Run a check in a browser without any Centreon or Keycloak session. */
export const inFreshBrowser = async (
  browser: Browser,
  check: (page: Page) => Promise<void>
): Promise<void> => {
  const context = await browser.newContext();
  try {
    await check(await context.newPage());
  } finally {
    await context.close();
  }
};

/** Sign a realm user in from the "Login with <provider>" button. */
export const signInThroughKeycloak = async (
  page: Page,
  provider: string,
  credentials: Credentials
): Promise<void> => {
  const login = new LoginPage(page);
  await login.open();
  await login.loginWith(provider);
  const keycloak = new KeycloakLoginPage(page);
  await keycloak.expectVisible();
  await keycloak.login(credentials);
};

export const expectSignedIn = async (page: Page): Promise<void> => {
  await expect(page).toHaveURL(/\/monitoring\/resources/, { timeout: 30_000 });
  await new MainHeader(page).expectLoaded();
};

export const expectDenied = async (page: Page): Promise<void> => {
  await expect(page).toHaveURL(/\/authentication-denied/, { timeout: 30_000 });
  await expect(page.getByText('You are not allowed to log in')).toBeVisible();
};

/** A local contact for a realm user, who can open Resources Status. */
export const localContactFor = async (
  adminApi: CentreonApi,
  alias: string
): Promise<RestrictedUser> => {
  const user = restrictedUser({
    menus: [{ access: 'rw', topology: 'Monitoring;Resources Status' }],
    name: alias
  });
  await adminApi.cleanUp(user.teardown);
  await adminApi.provision(user.provisioning);

  return user;
};

export interface Named {
  id: number;
  name: string;
}

export interface ImportTargets {
  aclGroups: Array<Named>;
  contactGroup: Named;
  /** The contact the import created for the user. */
  importedContact: () => { email: string; name: string; template: string };
  /** Names of its ACL groups and contact groups. */
  memberships: () => Array<string>;
  teardown: Array<ClapiAction>;
  template: Named;
}

const action = (object: string, verb: string, values: string): ClapiAction => ({
  action: verb,
  object,
  values
});

const idOf = (sql: string): number => Number(queryCentreonDb(sql).trim());

/**
 * The objects an auto import maps the contact `alias` to: a contact template,
 * ACL groups granting Resources Status, and a contact group.
 */
export const importTargets = async (
  adminApi: CentreonApi,
  alias: string,
  aclGroupCount = 1
): Promise<ImportTargets> => {
  const prefix = `pw-sso-${randomUUID().slice(0, 6)}`;
  const template = `${prefix}-template`;
  const menu = `${prefix}-menu`;
  const contactGroup = `${prefix}-cg`;
  const groups = Array.from(
    { length: aclGroupCount },
    (_, index) => `${prefix}-group-${index + 1}`
  );
  const ofContact = `JOIN contact c ON c.contact_id = r.contact_contact_id WHERE c.contact_alias = '${alias}'`;

  await adminApi.cleanUp([action('CONTACT', 'DEL', alias)]);
  await adminApi.provision([
    action('CONTACTTPL', 'ADD', `${template};${template};;0;1;en_US;local`),
    action('ACLMENU', 'ADD', `${menu};${menu}`),
    action('ACLMENU', 'GRANTRW', `${menu};1;Monitoring;Resources Status`),
    ...groups.flatMap((group) => [
      action('ACLGROUP', 'ADD', `${group};${group}`),
      action('ACLGROUP', 'ADDMENU', `${group};${menu}`)
    ]),
    action('CG', 'ADD', `${contactGroup};${contactGroup}`)
  ]);

  return {
    aclGroups: groups.map((name) => ({
      id: idOf(
        `SELECT acl_group_id FROM acl_groups WHERE acl_group_name = '${name}'`
      ),
      name
    })),
    contactGroup: {
      id: idOf(
        `SELECT cg_id FROM contactgroup WHERE cg_name = '${contactGroup}'`
      ),
      name: contactGroup
    },
    importedContact: () => {
      const [name, email, templateName] = queryCentreonDb(
        `SELECT c.contact_name, c.contact_email, t.contact_name FROM contact c JOIN contact t ON t.contact_id = c.contact_template_id WHERE c.contact_alias = '${alias}'`
      )
        .trim()
        .split('\t');

      return { email, name, template: templateName };
    },
    memberships: () =>
      queryCentreonDb(
        `SELECT g.acl_group_name FROM acl_group_contacts_relations r JOIN acl_groups g ON g.acl_group_id = r.acl_group_id ${ofContact} UNION SELECT cg.cg_name FROM contactgroup_contact_relation r JOIN contactgroup cg ON cg.cg_id = r.contactgroup_cg_id ${ofContact}`
      )
        .trim()
        .split('\n'),
    teardown: [
      action('CONTACT', 'DEL', alias),
      action('CONTACTTPL', 'DEL', template),
      ...groups.map((group) => action('ACLGROUP', 'DEL', group)),
      action('ACLMENU', 'DEL', menu),
      action('CG', 'DEL', contactGroup)
    ],
    template: {
      id: idOf(
        `SELECT contact_id FROM contact WHERE contact_name = '${template}'`
      ),
      name: template
    }
  };
};
