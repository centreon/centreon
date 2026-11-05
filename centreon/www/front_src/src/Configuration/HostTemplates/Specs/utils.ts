// Inline PNG: `useLoadImage` only needs `onload` to fire.
export const hostTemplateIcon =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFUlEQVR42mNg6O4mDY1qGNUwfDUAAED3FhAEZJ4nAAAAAElFTkSuQmCC';

// Rows: 1 full; 2 without alias/icon (`skip_null_values`); 3 locked, null icon, two parents.
// `totalItems` exceeds the page; undeclared JSON-LD keys (`@id`/`@type`) must be ignored.
const genid = (id: string): string => `/centreon/api/.well-known/genid/${id}`;

export const getListingResponse = () => ({
  '@context': '/centreon/api/contexts/HostTemplate',
  '@id': '/centreon/api/configuration/host_templates',
  '@type': 'Collection',
  member: [
    {
      '@id': '/centreon/api/configuration/host_templates/1',
      '@type': 'HostTemplate',
      alias: 'Generic active host',
      icon: {
        '@id': genid('8f3c2a1b9e7d4c6a5b0f'),
        '@type': 'Icon',
        id: 5,
        name: 'centreon.png',
        url: hostTemplateIcon
      },
      id: 1,
      is_locked: false,
      linked_service_templates_count: 3,
      name: 'generic-active-host-custom',
      parent_templates: [
        {
          '@id': genid('1d9e4b7c2a6f3e8b0c5a'),
          '@type': 'ParentTemplate',
          id: 2,
          name: 'generic-active-host'
        }
      ]
    },
    {
      '@id': '/centreon/api/configuration/host_templates/2',
      '@type': 'HostTemplate',
      id: 2,
      is_locked: false,
      linked_service_templates_count: 0,
      name: 'generic-active-host',
      parent_templates: []
    },
    {
      '@id': '/centreon/api/configuration/host_templates/3',
      '@type': 'HostTemplate',
      alias: 'Linux SNMP',
      icon: null,
      id: 3,
      is_locked: true,
      linked_service_templates_count: 12,
      name: 'OS-Linux-SNMP-custom',
      parent_templates: [
        {
          '@id': genid('6b2e9f4a1c8d7e3b5a0c'),
          '@type': 'ParentTemplate',
          id: 2,
          name: 'generic-active-host'
        },
        {
          '@id': genid('3a7c1e9b5d2f8a4c6e0b'),
          '@type': 'ParentTemplate',
          id: 4,
          name: 'OS-Linux-SNMP'
        }
      ]
    }
  ],
  totalItems: 30
});

export const emptyListingResponse = {
  '@context': '/centreon/api/contexts/HostTemplate',
  '@id': '/centreon/api/configuration/host_templates',
  '@type': 'Collection',
  member: [],
  totalItems: 0
};

// The detail of each template the listing names, parents included. Template
// 2's leaves its alias out; templates 3 and 4 are locked.
const hostTemplateDetails: Record<number, Record<string, unknown>> = {
  1: {
    alias: 'Generic active host',
    // Its own macro, then one its check command lends.
    check_options: {
      args: ['3', '80%'],
      command: { id: 9, name: 'check-host-alive' },
      macros: [
        { id: 1, is_password: false, name: 'SNMPPORT', value: '161' },
        {
          id: 4,
          is_password: false,
          name: 'WARNING',
          parent: 'command',
          value: '90'
        }
      ]
    },
    is_locked: false,
    name: 'generic-active-host-custom',
    snmp_version: '2c'
  },
  2: { is_locked: false, name: 'generic-active-host' },
  3: { alias: 'Linux SNMP', is_locked: true, name: 'OS-Linux-SNMP-custom' },
  4: { alias: 'Linux SNMP base', is_locked: true, name: 'OS-Linux-SNMP' }
};

// Template 2 has check arguments but no check command of its own.
const checkOptionsWithoutCommand: Record<number, Record<string, unknown>> = {
  2: { check_options: { args: ['80', 'graceful'] } }
};

export const getHostTemplateResponse = (id: number) => ({
  '@context': '/centreon/api/contexts/HostTemplate',
  '@id': `/centreon/api/configuration/host_templates/${id}`,
  '@type': 'HostTemplate',
  id,
  ...hostTemplateDetails[id],
  ...checkOptionsWithoutCommand[id]
});

const toCollection = (
  path: string,
  member: Array<Record<string, unknown>>
) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': `/centreon/api/configuration/host_templates/${path}`,
  '@type': 'Collection',
  member,
  totalItems: member.length
});

export const getFormCommandsResponse = () =>
  toCollection('commands', [
    { id: 8, name: 'check-centreon-ping' },
    { id: 9, name: 'check-host-alive' }
  ]);
