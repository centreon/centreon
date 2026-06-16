export interface Credentials {
  login: string;
  password: string;
}

/**
 * Default credentials provisioned by the Centreon docker image.
 * These are well-known, non-sensitive test credentials baked into the image.
 */
export const adminUser: Credentials = {
  login: process.env.CENTREON_ADMIN_LOGIN ?? 'admin',
  password: process.env.CENTREON_ADMIN_PASSWORD ?? 'Centreon!2021'
};

export const invalidUser: Credentials = {
  login: adminUser.login,
  password: 'wrong-password'
};
