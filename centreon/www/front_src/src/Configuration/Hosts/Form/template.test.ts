import { mergeAll, mergeDeepRight } from 'ramda';

import { getHostDecoder } from '../api/decoders';
import { getDefaultValues } from './defaultValues';
import { getMassChangeAdapter, getMassChangeDefaultValues } from './massChange';
import { getAvailableSections, type PlatformContext } from './sections';

const template: PlatformContext = {
  isAdditiveInheritanceEnabled: false,
  isCloudPlatform: false,
  isTemplate: true
};
const host: PlatformContext = { ...template, isTemplate: false };

const toPayload = (
  values: Record<string, unknown>,
  context: PlatformContext
): Record<string, unknown> =>
  mergeAll(
    getAvailableSections(context).map(({ section }) =>
      section.toPayload(values, context)
    )
  );

const values = {
  ...getDefaultValues(template),
  address: '10.0.0.1',
  alias: ' Generic ',
  categories: [{ id: 4, name: 'Virtual' }],
  groups: [{ id: 1, name: 'Linux servers' }],
  name: ' generic ',
  poller: { id: 2, name: 'Poller EU' },
  serviceTemplates: [{ id: 31, name: 'Base-Ping-LAN' }]
};

describe('a host template payload', () => {
  it('carries the template relations and none of a host field', () => {
    const payload = toPayload(values, template);

    expect(payload).toMatchObject({
      alias: 'Generic',
      category_ids: [4],
      name: 'generic',
      service_template_ids: [31]
    });
    [
      'address',
      'poller_id',
      'create_services_linked_to_templates',
      'host_group_ids',
      'parent_host_ids',
      'child_host_ids'
    ].forEach((key) => {
      expect(payload).not.toHaveProperty(key);
    });
    expect(payload.extended_informations).not.toHaveProperty('geo_coordinates');
  });

  it('leaves a host payload as it was, without the template relations', () => {
    const payload = toPayload(values, host);

    expect(payload).toMatchObject({
      address: '10.0.0.1',
      host_group_ids: [1],
      poller_id: 2
    });
    expect(payload).not.toHaveProperty('service_template_ids');
  });
});

describe('a host template detail', () => {
  // The host detail's keys a template does not have are absent, not null.
  const detail = {
    alias: 'Generic',
    categories: [],
    is_locked: true,
    name: 'generic',
    service_templates: [{ id: 31, name: 'Base-Ping-LAN' }]
  };

  it('decodes without the fields only a host has', async () => {
    const decoded = (await getHostDecoder(template).decodeToPromise(
      detail
    )) as unknown as Record<string, unknown>;

    expect(decoded.isLocked).toBe(true);
    expect(decoded.serviceTemplates).toEqual([
      { id: 31, name: 'Base-Ping-LAN' }
    ]);
    expect(decoded).not.toHaveProperty('poller');
  });

  it('still requires the poller of a host', async () => {
    await expect(
      getHostDecoder(host).decodeToPromise(detail)
    ).rejects.toBeTruthy();
  });
});

describe('a host template mass change', () => {
  const context = { ...template, isMassChange: true };

  const apply = (changes: Record<string, unknown>): object =>
    getMassChangeAdapter(context)(
      mergeDeepRight(getMassChangeDefaultValues(context), changes) as Record<
        string,
        unknown
      >
    );

  it('offers an update mode for the template lists only', () => {
    expect(
      Object.keys(
        getMassChangeDefaultValues(context).massChangeModes as object
      ).sort()
    ).toEqual(
      [
        'categories',
        'contacts',
        'notificationOptions',
        'serviceTemplates',
        'templates'
      ].sort()
    );
    expect(getMassChangeDefaultValues(context)).not.toHaveProperty(
      'createServicesLinkedToTemplates'
    );
  });

  it('adds the linked service templates, or replaces them', () => {
    const serviceTemplates = [{ id: 31, name: 'Base-Ping-LAN' }];

    expect(apply({ serviceTemplates })).toEqual({
      service_template_ids_to_add: [31]
    });
    expect(
      apply({
        massChangeModes: { serviceTemplates: 'replacement' },
        serviceTemplates
      })
    ).toEqual({ service_template_ids: [31] });
  });
});
