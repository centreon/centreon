import type { TFunction } from 'i18next';
import { array, object } from 'yup';

import type { SectionContext } from '../models';
import { relationList } from './relationList';

const t = ((label: string) => label) as TFunction;
const context: SectionContext = { isCloudPlatform: true, t };

const groups = relationList({
  decoderNames: ['Group', 'Groups'],
  endpoint: '/groups',
  getSchema: () =>
    array().test('not-forbidden', 'forbidden group', (list) =>
      (list ?? []).every(({ id }) => id !== 666)
    ),
  id: 'groups',
  isRequired: ({ isCloudPlatform }) => isCloudPlatform,
  label: 'Groups',
  name: 'groups',
  payloadKey: 'group_ids',
  resource: { baseEndpoint: '/hosts', idPrefix: 'host-form' }
});

const validate = (list: Array<{ id: number; name: string }>, at = context) =>
  object(groups.getSchema(at)).validateSync({ groups: list });

describe('Relation list', () => {
  it('requires an entity and applies its own rule', () => {
    expect(() => validate([])).toThrow('Required');
    expect(() => validate([{ id: 666, name: 'forbidden' }])).toThrow(
      'forbidden group'
    );
    expect(() => validate([{ id: 1, name: 'linux' }])).not.toThrow();
  });

  it('applies its own rule alone where none is required', () => {
    const onPremise: SectionContext = { isCloudPlatform: false, t };

    expect(() => validate([], onPremise)).not.toThrow();
    expect(() => validate([{ id: 666, name: 'forbidden' }], onPremise)).toThrow(
      'forbidden group'
    );
  });
});
