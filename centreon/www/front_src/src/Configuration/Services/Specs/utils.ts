// A 16x16 inline PNG rather than a committed fixture: `useLoadImage` only needs
// `new Image()` to fire `onload`, which a data URI does.
export const iconUrl =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFUlEQVR42mNg6O4mDY1qGNUwfDUAAED3FhAEZJ4nAAAAAElFTkSuQmCC';

// Unset fields are absent from the JSON entirely, so service 1 carries neither
// an icon, intervals nor a template.
export const getListingResponse = () => ({
  '@context': '/centreon/api/contexts/Service',
  '@id': '/centreon/api/configuration/services',
  '@type': 'Collection',
  member: [
    {
      activated: true,
      host: {
        icon: { id: 12, name: 'server.png', url: iconUrl },
        id: 10,
        name: 'host A'
      },
      icon: { id: 13, name: 'cpu.png', url: iconUrl },
      id: 0,
      name: 'cpu',
      normal_check_interval: 5,
      retry_check_interval: 1,
      template: { id: 30, name: 'generic-active-service' }
    },
    {
      activated: false,
      host: { id: 11, name: 'host B' },
      id: 1,
      name: 'memory'
    },
    // A second activated service: a deactivated row is not selectable, so
    // without it there is only one row a massive action can act on.
    {
      activated: true,
      host: { id: 10, name: 'host A' },
      id: 2,
      name: 'disk',
      normal_check_interval: 3,
      retry_check_interval: 2
    }
  ],
  totalItems: 3
});

export const emptyListingResponse = {
  '@context': '/centreon/api/contexts/Service',
  '@id': '/centreon/api/configuration/services',
  '@type': 'Collection',
  member: [],
  totalItems: 0
};

export const getServiceTemplatesResponse = () => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration/services/service_templates',
  '@type': 'Collection',
  member: [
    { id: 30, name: 'generic-active-service' },
    { id: 31, name: 'generic-passive-service' }
  ],
  totalItems: 2
});

// The detail of each listed service, as the API Platform detail route answers:
// the host as an object where the create takes its id.
const serviceDetails: Record<number, Record<string, unknown>> = {
  0: {
    activated: true,
    host: { id: 10, name: 'host A' },
    name: 'cpu',
    // The template and the scheduling options.
    scheduling_options: {
      active_check_enabled: 'false',
      check_period: { id: 21, name: 'workhours' },
      is_volatile: 'true',
      max_check_attempts: 3,
      normal_check_interval: 5,
      passive_check_enabled: 'use_default',
      retry_check_interval: null
    },
    template: { id: 30, name: 'generic-active-service' }
  },
  // Unset fields left out: no template, no scheduling options.
  1: { activated: false, host: { id: 11, name: 'host B' }, name: 'memory' },
  // A cloud service always has a template.
  2: {
    activated: true,
    host: { id: 10, name: 'host A' },
    name: 'disk',
    template: { id: 31, name: 'generic-passive-service' }
  }
};

// Service 0 has its own command, arguments and macros: its own, a stored
// password (no value sent back) and one inherited from its template. Service 1
// has only a command, which a service without a template needs to be saved;
// service 2 none: the key is left out.
export const getServiceCheckOptionsResponse = (id: number) => {
  if (id === 0) {
    return {
      check_options: {
        args: ['80', '90'],
        command: { id: 9, name: 'check-centreon-cpu' },
        macros: [
          { id: 1, is_password: false, name: 'WARNING', value: '80' },
          { id: 2, is_password: true, name: 'API_TOKEN' },
          {
            id: 11,
            is_password: false,
            name: 'TIMEOUT',
            parent: 'template',
            value: '10'
          }
        ]
      }
    };
  }

  return id === 1
    ? { check_options: { command: { id: 8, name: 'check-centreon-ping' } } }
    : {};
};

// Relations: the service groups are always sent, the traps on-premise only.
const getRelationsResponse = (id: number) => ({
  service_groups: id === 0 ? [{ id: 1, name: 'Web services' }] : [],
  traps:
    id === 0
      ? [{ id: 21, name: 'linkDown', vendor: { id: 3, name: 'Generic' } }]
      : []
});

export const getServiceResponse = (id: number) => ({
  '@context': '/centreon/api/contexts/Service',
  '@id': `/centreon/api/configuration/services/${id}`,
  '@type': 'Service',
  id,
  ...serviceDetails[id],
  ...getServiceCheckOptionsResponse(id),
  ...getRelationsResponse(id)
});

export const getServiceFormHostsResponse = () => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration/services/hosts',
  '@type': 'Collection',
  member: [
    { id: 10, name: 'host A' },
    { id: 11, name: 'host B' }
  ],
  totalItems: 2
});

// The check period selector.
export const getServiceFormTimePeriodsResponse = () => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration/services/timeperiods',
  '@type': 'Collection',
  member: [
    { id: 20, name: '24x7' },
    { id: 21, name: 'workhours' }
  ],
  totalItems: 2
});

export const getServiceFormCommandsResponse = () => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration/services/commands',
  '@type': 'Collection',
  member: [
    { id: 8, name: 'check-centreon-ping' },
    { id: 9, name: 'check-centreon-cpu' }
  ],
  totalItems: 2
});

// One service template's macros, its own then those it inherits, for when the
// template field (F03) can pick one.
export const getServiceTemplateMacrosResponse = () => ({
  id: 30,
  macros: [{ id: 31, is_password: false, name: 'TIMEOUT', value: '10' }],
  name: 'generic-active-service'
});

const toCollection = (path: string, member: Array<object>) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': `/centreon/api/configuration/services/${path}`,
  '@type': 'Collection',
  member,
  totalItems: member.length
});

export const getServiceGroupsResponse = () =>
  toCollection('service_groups', [
    { id: 1, name: 'Web services' },
    { id: 2, name: 'Database services' }
  ]);

// The vendor apart, as `TrapChoicesOutput` sends it.
export const getTrapsResponse = () =>
  toCollection('traps', [
    { id: 21, name: 'linkDown', vendor: { id: 3, name: 'Generic' } },
    { id: 22, name: 'linkUp', vendor: { id: 3, name: 'Generic' } }
  ]);
