import { mergeDeepRight } from 'ramda';

import { labelWarning } from '../translatedLabels';
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

const webServers = { id: 1, name: 'Web servers' };
const admin = { id: 3, name: 'admin' };
const period = { id: 2, name: '24x7' };

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

const withMode = (field: string, mode: string): Record<string, unknown> => ({
  massChangeModes: { [field]: mode }
});

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

  it('starts the fields a user can empty as untouched rather than empty', () => {
    const values = getMassChangeDefaultValues(onPremise) as Record<
      string,
      Record<string, unknown>
    >;

    expect(values.schedulingOptions.maxCheckAttempts).toBeUndefined();
    expect(values.schedulingOptions.checkPeriod).toBeUndefined();
    expect(values.template).toBeUndefined();
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
        serviceGroups: [webServers],
        traps: [{ id: 5, name: 'linkDown' }]
      })
    ).toEqual({
      category_ids_to_add: [4],
      service_group_ids_to_add: [1],
      trap_ids_to_add: [5]
    });
  });

  it('replaces a list set to Replacement, emptying it when nothing is picked', () => {
    expect(
      apply({
        ...mergeDeepRight(
          withMode('serviceGroups', 'replacement'),
          withMode('traps', 'replacement')
        ),
        serviceGroups: [webServers]
      })
    ).toEqual({ service_group_ids: [1], trap_ids: [] });
  });

  it('removes from each service what a list set to Removal picks, and sends nothing when it picks nothing', () => {
    expect(
      apply({
        ...withMode('categories', 'removal'),
        categories: [{ id: 4, name: 'Ping' }]
      })
    ).toEqual({ category_ids_to_remove: [4] });
    expect(apply(withMode('categories', 'removal'))).toEqual({});
  });

  it('applies the shared contacts mode to contacts and contact groups alike', () => {
    expect(apply(withMode('contacts', 'replacement'))).toEqual({
      notifications: { contact_groups: [], contacts: [] }
    });
    expect(
      apply({
        ...withMode('contacts', 'removal'),
        notifications: { contacts: [admin] }
      })
    ).toEqual({ notifications: { contacts_to_remove: [3] } });
  });

  it('adds contacts and notification options next to the other notification changes', () => {
    expect(
      apply({
        notifications: {
          contacts: [admin],
          interval: 5,
          options: [labelWarning]
        }
      })
    ).toEqual({
      notifications: {
        contacts_to_add: [3],
        interval: 5,
        options_to_add: ['warning']
      }
    });
  });

  it('sends a field emptied on purpose as null, and nothing for one left untouched', () => {
    expect(
      apply({
        extendedInfos: { note: '' },
        notifications: { timeperiod: null },
        schedulingOptions: { maxCheckAttempts: '' },
        template: null
      })
    ).toEqual({
      extended_informations: { note: null },
      notifications: { timeperiod_id: null },
      scheduling_options: { max_check_attempts: null },
      service_template_id: null
    });
  });

  it('sends a picked value of a clearable field as is', () => {
    expect(
      apply({
        notifications: { timeperiod: period },
        schedulingOptions: { maxCheckAttempts: 3 }
      })
    ).toEqual({
      notifications: { timeperiod_id: 2 },
      scheduling_options: { max_check_attempts: 3 }
    });
  });

  it('sends a picked Yes or No as a boolean, and nothing for the switch left unpicked', () => {
    expect(
      apply({ notifications: { contactAdditiveInheritance: 'false' } })
    ).toEqual({ notifications: { contact_additive_inheritance: false } });
    expect(
      apply({ notifications: { useOnlyContactsFromHost: 'true' } })
    ).toEqual({
      notifications: {
        contact_additive_inheritance: false,
        contact_group_additive_inheritance: false,
        use_only_contacts_from_host: true
      }
    });
  });

  it('sends Default when it is picked on purpose', () => {
    expect(
      apply({ schedulingOptions: { activeCheckEnabled: 'use_default' } })
    ).toEqual({ scheduling_options: { active_check_enabled: 'use_default' } });
  });

  it('sends only what changed on cloud, where traps and notifications do not exist', () => {
    expect(
      apply(
        {
          ...withMode('traps', 'replacement'),
          serviceGroups: [webServers]
        },
        cloud
      )
    ).toEqual({ service_group_ids_to_add: [1] });
  });
});

describe('getMassChangeWarning', () => {
  const getWarning = getMassChangeWarning(((label: string) => label) as never);

  it('warns only when a list is set to Replacement', () => {
    const values = getMassChangeDefaultValues(onPremise) as Record<
      string,
      unknown
    >;

    expect(getWarning(values)).toBeNull();
    expect(
      getWarning(mergeDeepRight(values, withMode('categories', 'removal')))
    ).toBeNull();
    expect(
      getWarning(mergeDeepRight(values, withMode('categories', 'replacement')))
    ).not.toBeNull();
  });
});
