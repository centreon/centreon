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

// One service linked to two hosts: the API returns it once per host, with the
// same id.
export const getSharedServiceListingResponse = () => ({
  ...getListingResponse(),
  member: [
    { activated: true, host: { id: 10, name: 'host A' }, id: 5, name: 'ping' },
    { activated: true, host: { id: 11, name: 'host B' }, id: 5, name: 'ping' }
  ],
  totalItems: 2
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
// the host as an object where the create takes its id, `monitoring_engine`
// always there, and unset values left out.
const serviceDetails: Record<number, Record<string, unknown>> = {
  0: {
    activated: true,
    host: { id: 10, name: 'host A' },
    // The template and the scheduling options.
    monitoring_engine: {
      active_check_enabled: 'false',
      check_period: { id: 21, name: 'workhours' },
      is_volatile: 'true',
      max_check_attempts: 3,
      normal_check_interval: 5,
      passive_check_enabled: 'use_default'
    },
    name: 'cpu',
    template: { id: 30, name: 'generic-active-service' }
  },
  // No template, and the scheduling on its defaults.
  1: {
    activated: false,
    host: { id: 11, name: 'host B' },
    monitoring_engine: {
      active_check_enabled: 'use_default',
      is_volatile: 'use_default',
      passive_check_enabled: 'use_default'
    },
    name: 'memory'
  },
  // A cloud service always has a template, and no check switches.
  2: {
    activated: true,
    host: { id: 10, name: 'host A' },
    monitoring_engine: {},
    name: 'disk',
    template: { id: 31, name: 'generic-passive-service' }
  }
};

export const getServiceResponse = (id: number) => ({
  '@context': '/centreon/api/contexts/Service',
  '@id': `/centreon/api/configuration/services/${id}`,
  '@type': 'Service',
  id,
  ...serviceDetails[id]
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
