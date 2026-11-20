import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array, object } from 'yup';

import type { SectionContext } from '../models';
import { getRelationListBlock } from './relationList';

const t = ((label: string) => label) as TFunction;
const context: SectionContext = { isCloudPlatform: true, t };

const groups = getRelationListBlock({
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

describe('Relation list with options of its own', () => {
  const traps = getRelationListBlock({
    decoderNames: ['Trap', 'Traps'],
    endpoint: '/traps',
    entityDecoder: JsonDecoder.object(
      {
        id: JsonDecoder.number,
        name: JsonDecoder.string,
        vendor: JsonDecoder.string
      },
      'Trap'
    ).map(({ id, name, vendor }) => ({ id, name: `${vendor} - ${name}` })),
    id: 'snmpTraps',
    isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
    label: 'Traps',
    name: 'traps',
    payloadKey: 'trap_ids',
    resource: { baseEndpoint: '/services', idPrefix: 'service-form' }
  });

  it('reads each entity with its own decoder', () => {
    const decoded = JsonDecoder.object(traps.detailDecoders, 'Detail').decode({
      snmpTraps: [{ id: 1, name: 'linkDown', vendor: 'Generic' }]
    });

    expect(decoded.isOk() && decoded.value).toEqual({
      snmpTraps: [{ id: 1, name: 'Generic - linkDown' }]
    });
  });

  it('fails on a detail without the list, unless told it may leave it out', () => {
    const decode = (block: typeof traps) =>
      JsonDecoder.object(block.detailDecoders, 'Detail').decode({});

    expect(decode(traps).isOk()).toBe(false);

    const lenientTraps = getRelationListBlock({
      decoderNames: ['Trap', 'Traps'],
      endpoint: '/traps',
      id: 'snmpTraps',
      label: 'Traps',
      name: 'traps',
      payloadKey: 'trap_ids',
      resource: { baseEndpoint: '/services', idPrefix: 'service-form' },
      withOptionalDetailKey: true
    });
    const decoded = decode(lenientTraps);

    expect(decoded.isOk() && decoded.value).toEqual({ snmpTraps: [] });
  });

  it('exists where it is available only', () => {
    expect(traps.isAvailable?.({ isCloudPlatform: false })).toBe(true);
    expect(traps.isAvailable?.({ isCloudPlatform: true })).toBe(false);
    expect(groups.isAvailable).toBeUndefined();
  });
});
