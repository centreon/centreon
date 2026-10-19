import type { CentreonApi } from '../helpers/CentreonApi';

export type ProviderSettings = Record<string, unknown>;

interface ReadRelation {
  access_group?: { id: number };
  claim_value?: string;
  contact_group?: { id: number };
  group_value?: string;
  priority?: number;
}

interface Mapping {
  relations: Array<ReadRelation>;
}

/** The GET body names the related objects, the PUT body takes their ids. */
const writable = (settings: ProviderSettings): ProviderSettings => {
  const roles = settings.roles_mapping as Mapping | undefined;
  const groups = settings.groups_mapping as Mapping | undefined;

  return {
    ...settings,
    ...(groups && {
      groups_mapping: {
        ...groups,
        relations: groups.relations.map((r) => ({
          contact_group_id: r.contact_group?.id,
          group_value: r.group_value
        }))
      }
    }),
    ...(roles && {
      roles_mapping: {
        ...roles,
        relations: roles.relations.map((r) => ({
          access_group_id: r.access_group?.id,
          claim_value: r.claim_value,
          priority: r.priority
        }))
      }
    })
  };
};

/**
 * Configure an authentication provider through the API; `restore` puts back
 * the settings found beforehand.
 */
export const configureProvider = async (
  adminApi: CentreonApi,
  provider: 'openid' | 'saml' | 'web-sso',
  settings: ProviderSettings
): Promise<{ restore: () => Promise<void> }> => {
  const path = `/administration/authentication/providers/${provider}`;
  const original = await adminApi.v2<ProviderSettings>('GET', path);
  await adminApi.v2('PUT', path, settings);

  return {
    restore: async () => {
      await adminApi.v2('PUT', path, writable(original));
    }
  };
};
