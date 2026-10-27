import { hostIcon } from '../../Hosts/Specs/utils';

// The shape the listing is proposed to take: the hosts listing's, without
// what only a host has, plus `is_locked` and the linked service templates
// count. Unset fields are left out, as `skip_null_values` does.
export const getListingResponse = () => ({
  '@context': '/centreon/api/contexts/HostTemplate',
  '@id': '/centreon/api/configuration/host_templates',
  '@type': 'Collection',
  member: [
    {
      alias: 'Generic active host',
      icon: { id: 12, name: 'server.png', url: hostIcon },
      id: 5,
      is_locked: false,
      name: 'generic-active-host',
      service_templates_count: 2,
      templates: [{ id: 6, name: 'generic-passive-host' }]
    },
    {
      id: 6,
      is_locked: false,
      name: 'generic-passive-host',
      templates: []
    },
    {
      alias: 'Linux SNMP',
      id: 8,
      is_locked: true,
      name: 'OS-Linux-SNMP',
      service_templates_count: 4,
      templates: [{ id: 5, name: 'generic-active-host' }]
    }
  ],
  totalItems: 3
});

export const emptyListingResponse = {
  '@context': '/centreon/api/contexts/HostTemplate',
  '@id': '/centreon/api/configuration/host_templates',
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

export const getServiceTemplatesResponse = () =>
  toCollection([
    { id: 31, name: 'Base-Ping-LAN' },
    { id: 32, name: 'OS-Linux-CPU' }
  ]);

// The host detail's shape, without what only a host has (address, poller,
// groups, parent and child hosts, geographic coordinates) and with the
// template's own: `is_locked` and its linked service templates.
//
// Every value differs from the listing row of the same template, so a form
// filled from the carried row instead of from this response fails.
export const getHostTemplateResponse = ({ isLocked = false } = {}) => ({
  alias: 'alias of template 5 as the detail endpoint spells it',
  categories: [{ id: 4, name: 'Virtual' }],
  check_options: {
    args: ['3'],
    command: { id: 9, name: 'check-host-alive' },
    macros: [
      { id: 1, is_password: false, name: 'SNMPPORT', value: '161' },
      {
        id: 51,
        is_password: false,
        name: 'OS',
        parent: 'template',
        value: 'windows'
      }
    ]
  },
  data_processing: {
    check_freshness: 'true',
    event_handler_enabled: 'false',
    freshness_threshold: 120
  },
  extended_informations: {
    icon: { id: 12, name: 'server.png', url: hostIcon },
    note: 'Generic template'
  },
  is_locked: isLocked,
  name: 'template 5 as the detail endpoint spells it',
  notifications: {
    contact_groups: [{ id: 3, name: 'Supervisors' }],
    contacts: [{ id: 1, name: 'admin' }],
    enabled: 'true',
    options: ['down'],
    timeperiod: { id: 1, name: '24x7' }
  },
  scheduling_options: {
    active_check_enabled: 'true',
    max_check_attempts: 3,
    passive_check_enabled: 'use_default'
  },
  service_templates: [{ id: 31, name: 'Base-Ping-LAN' }],
  severity: { id: 2, name: 'Minor' },
  snmp_version: '2c',
  templates: [{ id: 6, name: 'generic-passive-host' }],
  timezone: { id: 7, name: 'Europe/Paris' }
});
