import { JsonDecoder } from 'ts.data.json';

import { getSnmpTrapsBlock, trapDecoder } from './snmpTraps';

const decodeTrap = (trap: unknown) => trapDecoder.decode(trap);

describe('trapDecoder', () => {
  it('reads a trap with its vendor as `vendor - trap`', () => {
    const decoded = decodeTrap({
      id: 21,
      name: 'linkDown',
      vendor: { id: 3, name: 'Generic' }
    });

    expect(decoded.isOk() && decoded.value).toEqual({
      id: 21,
      name: 'Generic - linkDown'
    });
  });

  it('reads a trap without vendor by its name alone', () => {
    const withNullVendor = decodeTrap({
      id: 21,
      name: 'linkDown',
      vendor: null
    });
    const withoutVendor = decodeTrap({ id: 21, name: 'linkDown' });

    expect(withNullVendor.isOk() && withNullVendor.value).toEqual({
      id: 21,
      name: 'linkDown'
    });
    expect(withoutVendor.isOk() && withoutVendor.value).toEqual({
      id: 21,
      name: 'linkDown'
    });
  });

  it('refuses a malformed vendor', () => {
    expect(
      decodeTrap({ id: 21, name: 'linkDown', vendor: { id: 'x' } }).isOk()
    ).toBe(false);
  });
});

describe('getSnmpTrapsBlock', () => {
  const block = getSnmpTrapsBlock({
    endpoint: '/configuration/services/traps',
    resource: {
      baseEndpoint: '/configuration/services',
      idPrefix: 'service-form'
    }
  });
  const decodeDetail = (detail: object) =>
    JsonDecoder.object(block.detailDecoders, 'Detail').decode(detail);

  it('reads the traps of a detail with their vendor', () => {
    const decoded = decodeDetail({
      snmpTraps: [
        { id: 21, name: 'linkDown', vendor: { id: 3, name: 'Generic' } }
      ]
    });

    expect(decoded.isOk() && decoded.value).toEqual({
      snmpTraps: [{ id: 21, name: 'Generic - linkDown' }]
    });
  });

  it('accepts a detail without traps', () => {
    const decoded = decodeDetail({});

    expect(decoded.isOk()).toBe(true);
  });
});
