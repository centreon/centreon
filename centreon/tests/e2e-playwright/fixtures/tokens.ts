import type { ClapiAction } from '../helpers/CentreonApi';

/**
 * Fixtures for the authentication-token (API token) specs, mirroring the
 * Cypress `Api-Token` feature: a couple of plain contacts an API token can be
 * bound to, plus a naming prefix so the specs only ever touch their own tokens
 * (the platform ships default `poller`/`cma` tokens that must be left alone).
 */

export interface TokenUser {
  alias: string;
  name: string;
}

export const tokenUsers: Array<TokenUser> = [
  { alias: 'apitoken_user_1', name: 'API token user 1' },
  { alias: 'apitoken_user_2', name: 'API token user 2' }
];

const contactPassword = 'Centreon@2023';

/** CLAPI actions provisioning the token users (idempotent: tolerate reruns). */
export const tokenUsersActions: Array<ClapiAction> = tokenUsers.flatMap(
  ({ alias, name }) => [
    {
      action: 'ADD',
      object: 'CONTACT',
      values: `${name};${alias};${alias}@centreon.test;${contactPassword};0;1;en_US;local`
    },
    {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: `${alias};reach_api;1`
    }
  ]
);
