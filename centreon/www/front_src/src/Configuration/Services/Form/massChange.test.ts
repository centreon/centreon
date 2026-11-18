import { mergeDeepRight } from 'ramda';

import { labelReplacementOverwritesLists } from '../translatedLabels';
import {
  getMassChangeAdapter,
  getMassChangeDefaultValues,
  getMassChangeWarning
} from './massChange';
import type { PlatformContext } from './sections';

const onPremise: PlatformContext = {
  isAdditiveInheritanceEnabled: true,
  isCloudPlatform: false,
  isMassChange: true
};
const cloud: PlatformContext = { isCloudPlatform: true, isMassChange: true };

const webGroup = { id: 1, name: 'Web' };
const admin = { id: 3, name: 'admin' };
const trap = { id: 7, name: 'linkDown' };

// The values of a mass change opened on `context`, then edited with `changes`.
const apply = (
  changes: Record<string, unknown>,
  context: PlatformContext = onPremise
): object =>
  getMassChangeAdapter(context)(
    mergeDeepRight(getMassChangeDefaultValues(context), changes) as Record<
      string,
      unknown
    >
  );

const withMode = (
  field: string,
  mode: 'replacement' | 'removal'
): Record<string, unknown> => ({ massChangeModes: { [field]: mode } });

describe('getMassChangeDefaultValues', () => {
  it('imposes nothing: no default tri-state, switches unpicked, every list incremental', () => {
    const values = getMassChangeDefaultValues(onPremise);

    expect(JSON.stringify(values)).not.toContain('use_default');
    expect(values.notifications).toMatchObject({
      contactAdditiveInheritance: null,
      contactGroupAdditiveInheritance: null,
      useOnlyContactsFromHost: null
    });
    expect(new Set(Object.values(values.massChangeModes))).toEqual(
      new Set(['incremental'])
    );
  });
});

describe('getMassChangeAdapter', () => {
  it('sends nothing for an untouched form, on premise and on cloud', () => {
    expect(apply({})).toEqual({});
    expect(apply({}, cloud)).toEqual({});
  });

  it('adds to each service what an incremental list picks', () => {
    expect(
      apply({
        categories: [{ id: 4, name: 'Ping' }],
        groups: [webGroup],
        notifications: { contacts: [admin] },
        snmpTraps: [trap]
      })
    ).toEqual({
      category_ids_to_add: [4],
      notifications: { contacts_to_add: [3] },
      service_group_ids_to_add: [1],
      trap_ids_to_add: [7]
    });
  });

  it('replaces a list set to Replacement, emptying it when nothing is picked', () => {
    expect(
      apply({
        ...mergeDeepRight(
          withMode('groups', 'replacement'),
          withMode('snmpTraps', 'replacement')
        ),
        groups: [webGroup]
      })
    ).toEqual({ service_group_ids: [1], trap_ids: [] });
  });

  it('removes from each service what a list set to Removal picks', () => {
    expect(
      apply({
        ...mergeDeepRight(
          withMode('categories', 'removal'),
          withMode('contacts', 'removal')
        ),
        categories: [{ id: 4, name: 'Ping' }],
        notifications: { contactGroups: [{ id: 2, name: 'ops' }] }
      })
    ).toEqual({
      category_ids_to_remove: [4],
      notifications: { contact_groups_to_remove: [2] }
    });
  });

  it('sends each list under its own mode in one body', () => {
    expect(
      apply({
        ...mergeDeepRight(
          withMode('categories', 'removal'),
          withMode('snmpTraps', 'replacement')
        ),
        categories: [{ id: 4, name: 'Ping' }],
        groups: [webGroup]
      })
    ).toEqual({
      category_ids_to_remove: [4],
      service_group_ids_to_add: [1],
      trap_ids: []
    });
  });

  it('sends nothing for a list set to Removal with nothing picked', () => {
    expect(apply(withMode('groups', 'removal'))).toEqual({});
  });

  it('clears both contacts and contact groups when their shared mode replaces with nothing', () => {
    expect(apply(withMode('contacts', 'replacement'))).toEqual({
      notifications: { contact_groups: [], contacts: [] }
    });
  });

  it('sends a picked switch as a boolean, and nothing for one left unpicked', () => {
    expect(
      apply({ notifications: { contactAdditiveInheritance: 'false' } })
    ).toEqual({ notifications: { contact_additive_inheritance: false } });
  });

  it('sends Default when it is picked on purpose', () => {
    expect(apply({ notifications: { enabled: 'use_default' } })).toEqual({
      notifications: { enabled: 'use_default' }
    });
  });

  it('sends only what changed on cloud, where the traps do not exist', () => {
    expect(apply({ groups: [webGroup] }, cloud)).toEqual({
      service_group_ids_to_add: [1]
    });
  });
});

describe('getMassChangeWarning', () => {
  const getWarning = getMassChangeWarning(((label: string) => label) as never);

  it('warns only when a list is replaced', () => {
    const values = getMassChangeDefaultValues(onPremise) as Record<
      string,
      unknown
    >;

    expect(getWarning(values)).toBeNull();
    expect(
      getWarning(mergeDeepRight(values, withMode('groups', 'removal')))
    ).toBeNull();
    expect(
      getWarning(mergeDeepRight(values, withMode('groups', 'replacement')))
    ).toBe(labelReplacementOverwritesLists);
  });
});
