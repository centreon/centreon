// A 16x16 inline PNG rather than a committed fixture: `useLoadImage` only needs
// `new Image()` to fire `onload`, which a data URI does.
export const hostIcon =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFUlEQVR42mNg6O4mDY1qGNUwfDUAAED3FhAEZJ4nAAAAAElFTkSuQmCC';

// `skip_null_values` is applied on this endpoint: an unset field is absent from
// the JSON entirely, so host 1 carries neither `alias` nor `icon`.
export const getListingResponse = () => ({
  '@context': '/centreon/api/contexts/Host',
  '@id': '/centreon/api/configuration/hosts',
  '@type': 'Collection',
  member: [
    {
      activated: true,
      address: '10.0.0.0',
      alias: 'alias for host 0',
      icon: { id: 12, name: 'server.png', url: hostIcon },
      id: 0,
      name: 'host 0',
      poller: { id: 1, name: 'Central' },
      templates: [
        { id: 5, name: 'generic-active-host' },
        { id: 6, name: 'generic-passive-host' }
      ]
    },
    {
      activated: false,
      address: '10.0.0.1',
      id: 1,
      name: 'host 1',
      poller: { id: 1, name: 'Central' },
      templates: []
    },
    // A second activated host: a deactivated row is not selectable, so without
    // this there is only one row a massive action can act on.
    {
      activated: true,
      address: '10.0.0.2',
      alias: 'alias for host 2',
      id: 2,
      name: 'host 2',
      poller: { id: 1, name: 'Central' },
      templates: []
    }
  ],
  totalItems: 3
});

export const emptyListingResponse = {
  '@context': '/centreon/api/contexts/Host',
  '@id': '/centreon/api/configuration/hosts',
  '@type': 'Collection',
  member: [],
  totalItems: 0
};

const toCollection = (member: Array<object>) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration',
  '@type': 'Collection',
  member,
  totalItems: member.length
});

export const getHostGroupsResponse = () =>
  toCollection([
    { id: 1, name: 'Linux servers' },
    { id: 2, name: 'Windows servers' }
  ]);

export const getPollersResponse = () =>
  toCollection([
    { id: 1, name: 'Central' },
    { id: 2, name: 'Poller EU' }
  ]);

export const getHostTemplatesResponse = () =>
  toCollection([
    { id: 5, name: 'generic-active-host' },
    { id: 6, name: 'generic-passive-host' }
  ]);
