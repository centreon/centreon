import { mergeDeepRight } from 'ramda';

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
    // The template and the scheduling options.
    monitoring_engine: {
      active_check_enabled: 'false',
      check_period: { id: 21, name: 'workhours' },
      is_volatile: 'true',
      max_check_attempts: 3,
      normal_check_interval: 5,
      passive_check_enabled: 'use_default',
      retry_check_interval: null
    },
    name: 'cpu',
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
// Service 1's detail leaves the traps out.
const getRelationsResponse = (id: number) => ({
  service_groups: id === 0 ? [{ id: 1, name: 'Web services' }] : [],
  ...(id !== 1 && {
    traps:
      id === 0
        ? [{ id: 21, name: 'linkDown', vendor: { id: 3, name: 'Generic' } }]
        : []
  })
});

// Extended info (F41-F50). Service 0 has every field but the note URL and the
// alt icon, which the API then leaves out; the others have none. Service 2's
// categories are an empty list as for hosts, service 1's are left out.
const getExtendedInfoDetail = (id: number): Record<string, unknown> => {
  if (id === 1) {
    return {};
  }

  return id === 0
    ? {
        // The comment sits with the data processing.
        advanced: { comment: 'Checked by the web team' },
        categories: [{ id: 3, name: 'Web' }],
        graph_template: { id: 7, name: 'CPU' },
        monitoring_engine: {
          action_url: 'https://example.com/actions/cpu',
          geo_coordinates: '48.8566,2.3522',
          icon: { id: 12, name: 'cpu.png', url: iconUrl },
          note: 'CPU load'
        },
        severity: { id: 1, level: 2, name: 'Major' }
      }
    : { categories: [] };
};

// `recovery_delay` left out, as the API leaves unset values out.
export const getServiceNotificationsResponse = () => ({
  contact_additive_inheritance: true,
  contact_group_additive_inheritance: false,
  contact_groups: [{ id: 3, name: 'Supervisors' }],
  contacts: [{ id: 1, name: 'admin' }],
  enabled: 'false',
  first_delay: 1,
  interval: 3,
  options: ['warning', 'critical', 'recovery'],
  timeperiod: { id: 20, name: '24x7' },
  use_only_contacts_from_host: false
});

export const getServiceResponse = (id: number) => ({
  '@context': '/centreon/api/contexts/Service',
  '@id': `/centreon/api/configuration/services/${id}`,
  '@type': 'Service',
  id,
  // Merged: several sections fill the same group of the API.
  ...[
    getServiceCheckOptionsResponse(id),
    getRelationsResponse(id),
    getExtendedInfoDetail(id)
  ].reduce(
    (detail, section) => mergeDeepRight(detail, section),
    serviceDetails[id] as Record<string, unknown>
  ),
  // Service 0 carries notification settings, service 1 none, service 2 only
  // some of them.
  ...(id === 0 && { notifications: getServiceNotificationsResponse() }),
  ...(id === 2 && { notifications: { interval: 5 } })
});

const getSelectorResponse = (
  path: string,
  member: Array<Record<string, unknown>>
) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': `/centreon/api/configuration/services/${path}`,
  '@type': 'Collection',
  member,
  totalItems: member.length
});

export const getServiceFormHostsResponse = () =>
  getSelectorResponse('hosts', [
    { id: 10, name: 'host A' },
    { id: 11, name: 'host B' }
  ]);

// The check period selector.
export const getServiceFormTimePeriodsResponse = () =>
  getSelectorResponse('timeperiods', [
    { id: 20, name: '24x7' },
    { id: 21, name: 'workhours' }
  ]);

export const getServiceFormCommandsResponse = () =>
  getSelectorResponse('commands', [
    { id: 8, name: 'check-centreon-ping' },
    { id: 9, name: 'check-centreon-cpu' }
  ]);

// One service template's macros, its own then those it inherits, for when the
// template field (F03) can pick one.
export const getServiceTemplateMacrosResponse = () => ({
  id: 30,
  macros: [{ id: 31, is_password: false, name: 'TIMEOUT', value: '10' }],
  name: 'generic-active-service'
});

export const getServiceGroupsResponse = () =>
  getSelectorResponse('service_groups', [
    { id: 1, name: 'Web services' },
    { id: 2, name: 'Database services' }
  ]);

// The vendor apart, as `TrapChoicesOutput` sends it.
export const getTrapsResponse = () =>
  getSelectorResponse('traps', [
    { id: 21, name: 'linkDown', vendor: { id: 3, name: 'Generic' } },
    { id: 22, name: 'linkUp', vendor: { id: 3, name: 'Generic' } }
  ]);

export const getGraphTemplatesResponse = () =>
  getSelectorResponse('graph_templates', [
    { id: 7, name: 'CPU' },
    { id: 8, name: 'Memory' }
  ]);

export const getServiceCategoriesResponse = () =>
  getSelectorResponse('service_categories', [
    { id: 3, name: 'Web' },
    { id: 4, name: 'Database' }
  ]);

export const getServiceSeveritiesResponse = () =>
  getSelectorResponse('service_severities', [
    { id: 1, level: 2, name: 'Major' },
    { id: 2, level: 1, name: 'Critical' },
    // No level sent: shown by its name alone.
    { id: 3, name: 'Minor' }
  ]);

export const getServiceMediasResponse = () =>
  getSelectorResponse('medias', [
    { id: 12, name: 'cpu.png', url: iconUrl },
    { id: 13, name: 'disk.png', url: iconUrl }
  ]);

// The notification selectors. The notification period shares the check
// period's selector.
export const getContactsResponse = () =>
  getSelectorResponse('contacts', [
    { id: 1, name: 'admin' },
    { id: 2, name: 'guest' }
  ]);

export const getContactGroupsResponse = () =>
  getSelectorResponse('contact_groups', [
    { id: 3, name: 'Supervisors' },
    { id: 4, name: 'Guests' }
  ]);

// What a form opened on service 0 sends, its notifications untouched.
export const openedNotificationsPayload = {
  contact_groups: [3],
  contacts: [1],
  enabled: 'false',
  first_delay: 1,
  interval: 3,
  options: ['warning', 'critical', 'recovery'],
  recovery_delay: null,
  timeperiod_id: 20,
  use_only_contacts_from_host: false
};

// What a form that never touched the notifications sends.
export const untouchedNotificationsPayload = {
  contact_groups: [],
  contacts: [],
  enabled: 'use_default',
  first_delay: null,
  interval: null,
  options: [],
  recovery_delay: null,
  timeperiod_id: null,
  use_only_contacts_from_host: false
};
