import { mergeDeepRight } from 'ramda';

import { getMassChangeAdapter, getMassChangeDefaultValues } from './massChange';
import type { PlatformContext } from './sections';

const onPremise: PlatformContext = {
  isAdditiveInheritanceEnabled: true,
  isCloudPlatform: false,
  isMassChange: true
};
const cloud: PlatformContext = { isCloudPlatform: true, isMassChange: true };

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
  it('imposes nothing: no default tri-state, every list incremental', () => {
    const values = getMassChangeDefaultValues(onPremise);

    expect(JSON.stringify(values)).not.toContain('use_default');
    expect(values.massChangeModes).toEqual({
      categories: 'incremental',
      contacts: 'incremental',
      linkedServiceTemplates: 'incremental',
      notificationOptions: 'incremental',
      templates: 'incremental'
    });
  });
});

describe('getMassChangeAdapter', () => {
  it('sends nothing for an untouched form, on premise and on cloud', () => {
    expect(apply({})).toEqual({});
    expect(apply({}, cloud)).toEqual({});
  });

  it('adds to each template what an incremental list picks', () => {
    expect(
      apply({
        categories: [{ id: 4, name: 'Virtual' }],
        linkedServiceTemplates: [{ id: 31, name: 'generic-ping' }],
        templates: [{ id: 2, name: 'generic-active-host' }]
      })
    ).toEqual({
      category_ids_to_add: [4],
      linked_service_template_ids_to_add: [31],
      template_ids_to_add: [2]
    });
  });

  it('replaces a list set to Replacement, emptying it when nothing is picked', () => {
    expect(
      apply({
        ...mergeDeepRight(
          replace('templates'),
          replace('linkedServiceTemplates')
        ),
        templates: [{ id: 2, name: 'generic-active-host' }]
      })
    ).toEqual({ linked_service_template_ids: [], template_ids: [2] });
  });
});
