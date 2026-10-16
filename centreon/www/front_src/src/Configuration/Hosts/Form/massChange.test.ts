import { mergeDeepRight } from 'ramda';

import { labelDown } from '../translatedLabels';
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

const linux = { id: 1, name: 'Linux servers' };
const admin = { id: 3, name: 'admin' };

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

const replace = (field: string): Record<string, unknown> => ({
  massChangeModes: { [field]: 'replacement' }
});

describe('getMassChangeDefaultValues', () => {
  it('imposes nothing: no default tri-state, additive inheritances unpicked, every list incremental', () => {
    const values = getMassChangeDefaultValues(onPremise);

    expect(JSON.stringify(values)).not.toContain('use_default');
    expect(values.notifications).toMatchObject({
      contactAdditiveInheritance: null,
      contactGroupAdditiveInheritance: null
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

  it('adds to each host what an incremental list picks', () => {
    expect(
      apply({
        childHosts: [{ id: 8, name: 'child' }],
        parentHosts: [{ id: 9, name: 'parent' }],
        templates: [{ id: 5, name: 'generic-active-host' }]
      })
    ).toEqual({
      child_host_ids_to_add: [8],
      parent_host_ids_to_add: [9],
      template_ids_to_add: [5]
    });
  });

  it('replaces a list set to Replacement, emptying it when nothing is picked', () => {
    expect(
      apply({
        ...mergeDeepRight(replace('parentHosts'), replace('childHosts')),
        parentHosts: [{ id: 9, name: 'parent' }]
      })
    ).toEqual({ child_host_ids: [], parent_host_ids: [9] });
  });

  it('clears both contacts and contact groups when their shared mode replaces with nothing', () => {
    expect(apply(replace('contacts'))).toEqual({
      notifications: { contact_groups: [], contacts: [] }
    });
  });

  it('adds contacts without touching contact groups, next to the other notification changes', () => {
    expect(
      apply({
        notifications: { contacts: [admin], interval: 5, options: [labelDown] }
      })
    ).toEqual({
      notifications: {
        contacts_to_add: [3],
        interval: 5,
        options_to_add: ['down']
      }
    });
  });

  it('never sends two forms of one list', () => {
    const body = apply({
      ...replace('groups'),
      categories: [{ id: 4, name: 'Virtual' }],
      groups: [linux]
    }) as Record<string, unknown>;

    expect(body).toEqual({ category_ids_to_add: [4], host_group_ids: [1] });
  });

  it('sends a picked additive inheritance as a boolean, and nothing for the one left unpicked', () => {
    expect(
      apply({ notifications: { contactAdditiveInheritance: 'false' } })
    ).toEqual({ notifications: { contact_additive_inheritance: false } });
    expect(
      apply({ notifications: { contactGroupAdditiveInheritance: 'true' } })
    ).toEqual({ notifications: { contact_group_additive_inheritance: true } });
  });

  it('sends Default when it is picked on purpose', () => {
    expect(apply({ notifications: { enabled: 'use_default' } })).toEqual({
      notifications: { enabled: 'use_default' }
    });
  });

  it('sends only what changed on cloud', () => {
    expect(apply({ groups: [linux] }, cloud)).toEqual({
      host_group_ids_to_add: [1]
    });
  });
});

describe('getMassChangeWarning', () => {
  const getWarning = getMassChangeWarning(((label: string) => label) as never);

  it('warns only when the templates are replaced', () => {
    const values = getMassChangeDefaultValues(onPremise) as Record<
      string,
      unknown
    >;

    expect(getWarning(values)).toBeNull();
    expect(
      getWarning(mergeDeepRight(values, replace('templates')))
    ).not.toBeNull();
  });
});
