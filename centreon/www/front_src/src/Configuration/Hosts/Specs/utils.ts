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

// The default is neither the first poller nor the one the tests pick, nor the
// one host 0 runs on.
const pollers = [
  { id: 1, is_default: false, name: 'Central' },
  { id: 2, is_default: false, name: 'Poller EU' },
  { id: 3, is_default: true, name: 'Poller US' }
];

// One list for the listing filter and the form alike, both reading the API
// Platform selector; only the form's carries `is_default`, which the filter
// ignores.
export const getPollersResponse = () => toCollection(pollers);

export const resolvedAddressResponse = {
  hostname: 'srv-apache-02.example.com',
  ip: '192.168.1.42',
  resolved: true
};

// `ip` is left out, not null, when the name does not resolve.
export const unresolvedAddressResponse = {
  hostname: 'srv-apache-02.example.com',
  resolved: false
};

// The legacy shape the platform gives a validation failure.
export const refusedAddressResponse = {
  code: 422,
  message: '[hostname] This value must be an IPv4 address or a hostname.\n'
};

export const getHostCategoriesResponse = () =>
  toCollection([
    { id: 3, name: 'Physical' },
    { id: 4, name: 'Virtual' }
  ]);

export const getContactsResponse = () =>
  toCollection([
    { id: 1, name: 'admin' },
    { id: 2, name: 'guest' }
  ]);

export const getContactGroupsResponse = () =>
  toCollection([
    { id: 3, name: 'Supervisors' },
    { id: 4, name: 'Guests' }
  ]);

export const getTimePeriodsResponse = () =>
  toCollection([
    { id: 1, name: '24x7' },
    { id: 2, name: 'workhours' }
  ]);

// What a create sends when nothing of the Notification section was touched.
export const untouchedNotificationsPayload = {
  contact_groups: [],
  contacts: [],
  enabled: 'use_default',
  first_delay: null,
  interval: null,
  options: [],
  recovery_delay: null,
  timeperiod_id: null
};

export const getHostTemplatesResponse = () =>
  toCollection([
    { id: 5, name: 'generic-active-host' },
    { id: 6, name: 'generic-passive-host' }
  ]);

// The shape MON-210415 (#11809) gives the detail endpoint: objects where the
// create takes ids. Mocked here until it lands; nothing stubs it at runtime.
//
// Every value differs from the listing row of the same host, so a form filled
// from the carried row instead of from this response fails rather than passes.
export const getHostResponse = () => ({
  address: '10.10.10.10',
  categories: [{ id: 4, name: 'Virtual' }],
  child_hosts: [{ id: 2, name: 'host 2' }],
  groups: [{ id: 1, name: 'Linux servers' }],
  name: 'host 0 as the detail endpoint spells it',
  // `recovery_delay` is unset, so the endpoint leaves it out.
  notifications: {
    contact_additive_inheritance: true,
    contact_group_additive_inheritance: false,
    contact_groups: [{ id: 3, name: 'Supervisors' }],
    contacts: [{ id: 1, name: 'admin' }],
    enabled: 'false',
    first_delay: 1,
    interval: 3,
    options: ['down', 'recovery'],
    timeperiod: { id: 1, name: '24x7' }
  },
  parent_hosts: [{ id: 1, name: 'host 1' }],
  poller: { id: 2, name: 'Poller EU' }
});
