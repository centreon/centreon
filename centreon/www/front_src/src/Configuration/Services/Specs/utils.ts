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
  '@id': '/centreon/api/configuration/service_templates',
  '@type': 'Collection',
  member: [
    { id: 30, name: 'generic-active-service' },
    { id: 31, name: 'generic-passive-service' }
  ],
  totalItems: 2
});

// The detail endpoint is a placeholder until the backend exposes it; every
// section adds the fields it reads back.
export const getServiceResponse = (id: number) => ({
  '@context': '/centreon/api/contexts/Service',
  '@id': `/centreon/api/configuration/services/${id}`,
  '@type': 'Service',
  id,
  ...getServiceConfigurationResponse(id),
  // MON-213086: service 0 carries notification settings, service 1 none.
  ...(id === 0 && { notifications: getServiceNotificationsResponse() }),
  // MON-213084: Relations section.
  service_groups: [{ id: 1, name: 'Web services' }],
  traps: [{ id: 21, name: 'linkDown', vendor: 'Generic' }],
  // MON-213085: Data Processing section.
  ...getDataProcessingResponse(id),
  // MON-213087: Extended Info section.
  ...getExtendedInfoResponse(id),
  // MON-213083: check command, arguments and macros.
  ...getServiceCheckOptionsResponse(id)
});

// MON-213083: check command, arguments and macros (F04–F06).

const toCollection = (member: Array<object>) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': '/centreon/api/configuration',
  '@type': 'Collection',
  member,
  totalItems: member.length
});

export const getCommandsResponse = () =>
  toCollection([
    // MON-213085's event handler, from the same selector.
    { id: 7, name: 'restart-httpd' },
    { id: 8, name: 'check-centreon-ping' },
    { id: 9, name: 'check-centreon-cpu' },
    // Placeholder key: the selector's shape is not settled.
    {
      id: 10,
      is_centreon_monitoring_agent: true,
      name: 'cma-linux-cpu'
    }
  ]);

// Service 0 has its own command, arguments and macros: its own, a stored
// password (no value sent back) and one inherited from its template. The
// others have none: the key is left out.
export const getServiceCheckOptionsResponse = (id: number) =>
  id === 0
    ? {
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
      }
    : // A service with no template still needs a command to be saved.
      id === 1
      ? { check_options: { command: { id: 8, name: 'check-centreon-ping' } } }
      : {};

// One service template's macros, its own then those it inherits, for when the
// template field (F03) can pick one.
export const getServiceTemplateMacrosResponses = (): Record<
  number,
  object
> => ({
  30: {
    id: 30,
    macros: [{ id: 31, is_password: false, name: 'TIMEOUT', value: '10' }],
    name: 'generic-active-service'
  }
});

// MON-213087: Extended Info section (F41-F50). Service 0 has every field the
// section reads back but the note URL and alt icon; the others have none.
export const getExtendedInfoResponse = (id: number) =>
  id === 0
    ? {
        categories: [{ id: 3, name: 'Web' }],
        extended_informations: {
          action_url: 'https://example.com/actions/cpu',
          comment: 'Checked by the web team',
          geo_coordinates: '48.8566,2.3522',
          icon: { id: 12, name: 'cpu.png', url: iconUrl },
          note: 'CPU load'
        },
        graph_template: { id: 7, name: 'CPU' },
        severity: { id: 1, level: 2, name: 'Major' }
      }
    : {};

// Data Processing (MON-213085): service 0 sets every field, service 2 is read
// on a cloud platform, where only four fields exist; service 1 sets none.
const getDataProcessingResponse = (id: number) => {
  if (id === 0) {
    return {
      data_processing: {
        acknowledgment_timeout: 15,
        check_freshness: 'true',
        event_handler: { id: 7, name: 'restart-httpd' },
        event_handler_args: ['80', 'graceful'],
        event_handler_enabled: 'false',
        flap_detection_enabled: 'true',
        freshness_threshold: 120,
        high_flap_threshold: 50,
        low_flap_threshold: null,
        obsess_over_service: 'false',
        retain_non_status_information: 'use_default',
        retain_status_information: 'true',
        stalking_options: ['w', 'c']
      }
    };
  }

  if (id === 2) {
    return {
      data_processing: {
        check_freshness: 'false',
        event_handler: null,
        event_handler_enabled: 'true',
        freshness_threshold: 60
      }
    };
  }

  return {};
};

// MON-213084: Relations section selectors use `toCollection` above.

// MON-213082: Service Configuration, name, host, template and scheduling.
// Service 0 sets every field; the others leave the optional ones out, as the
// API does for unset fields.
const serviceNames = ['cpu', 'memory', 'disk'];

export const getServiceConfigurationResponse = (id: number) => ({
  activated: id !== 1,
  host: { id: 10, name: 'host A' },
  name: serviceNames[id] ?? `service ${id}`,
  ...(id === 0 && {
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
  }),
  // A cloud service always has a template: service 2 is opened on cloud.
  ...(id === 2 && { template: { id: 31, name: 'generic-passive-service' } })
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

export const getServiceFormServiceTemplatesResponse = () =>
  getSelectorResponse('service_templates', [
    { id: 30, name: 'generic-active-service' },
    { id: 31, name: 'generic-passive-service' }
  ]);

export const getServiceFormTimePeriodsResponse = () =>
  getSelectorResponse('timeperiods', [
    { id: 20, name: '24x7' },
    { id: 21, name: 'workhours' }
  ]);

// MON-213086: Notifications section.
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

export const getTimePeriodsResponse = () =>
  getSelectorResponse('timeperiods', [
    { id: 1, name: '24x7' },
    { id: 2, name: 'workhours' }
  ]);

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
  timeperiod: { id: 1, name: '24x7' },
  use_only_contacts_from_host: false
});

// What a form that never touched the section sends.
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
export const getServiceGroupsResponse = () =>
  toCollection([
    { id: 1, name: 'Web services' },
    { id: 2, name: 'Database services' }
  ]);

export const getTrapsResponse = () =>
  toCollection([
    { id: 21, name: 'linkDown', vendor: 'Generic' },
    { id: 22, name: 'linkUp', vendor: 'Generic' }
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
    { id: 2, level: 1, name: 'Critical' }
  ]);

export const getServiceMediasResponse = () =>
  getSelectorResponse('medias', [
    { id: 12, name: 'cpu.png', url: iconUrl },
    { id: 13, name: 'disk.png', url: iconUrl }
  ]);
