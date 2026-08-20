import adminFixture from '../../../e2e/fixtures/users/admin.json';

export interface Credentials {
  alias: string;
  password: string;
}

/**
 * Credentials of the containerized test platform. The admin ones are shared
 * with the Cypress suite so they stay defined in a single place.
 */
export const users = {
  admin: {
    alias: process.env.E2E_ADMIN_LOGIN ?? adminFixture.login,
    password: process.env.E2E_ADMIN_PASSWORD ?? adminFixture.password
  },
  unknown: {
    alias: 'not-a-centreon-user',
    password: 'not-a-valid-password'
  }
} satisfies Record<string, Credentials>;
