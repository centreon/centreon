import { pick } from 'ramda';

import { getPayload } from '../../ResourceForm/blocks';
import { blocks, type FormValues } from './sections';

// The keys of the name and host block: the other blocks' are their own tests'.
const identityKeys = ['activated', 'host_id', 'name'];

const payloadOf = (values: FormValues, { isMassChange = false } = {}) =>
  pick(
    identityKeys,
    getPayload(blocks, values, { isCloudPlatform: false, isMassChange })
  );

describe('Service payload', () => {
  it('sends the name as the server stores it', () => {
    expect(
      payloadOf({
        host: { id: 11, name: 'host B' },
        name: '\t cpu  \t load \n'
      })
    ).toEqual({ host_id: 11, name: 'cpu load' });
  });

  it('sends back the activation of an opened service', () => {
    expect(
      payloadOf({
        host: { id: 10, name: 'host A' },
        isActivated: false,
        name: 'cpu'
      })
    ).toEqual({ activated: false, host_id: 10, name: 'cpu' });
  });

  it('leaves the activation out of a new service', () => {
    expect(payloadOf({ host: null, name: undefined })).toEqual({
      host_id: null,
      name: ''
    });
  });

  it('sends no name or host to every service of a mass change', () => {
    expect(payloadOf({ host: null, name: '' }, { isMassChange: true })).toEqual(
      {}
    );
  });
});
