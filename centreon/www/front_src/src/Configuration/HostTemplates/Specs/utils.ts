// A 16x16 inline PNG rather than a committed fixture: `useLoadImage` only needs
// `new Image()` to fire `onload`, which a data URI does.
export const hostTemplateIcon =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFUlEQVR42mNg6O4mDY1qGNUwfDUAAED3FhAEZJ4nAAAAAElFTkSuQmCC';

// Three rows: two unlocked ones, so a selection has more than one row to take,
// and a locked one, which no selection may take.
// - 1 carries every field, its icon included;
// - 2 has its `alias` and `icon` left out, as `skip_null_values` sends them;
// - 3 is locked, with an explicit null icon and two parents.
// `totalItems` exceeds the page, so the listing offers a next page.
// As API Platform answers, every object carries JSON-LD keys the decoders do
// not declare: a member its `@id`/`@type`, a nested object (icon, parent) an
// anonymous `@type` and a generated `@id`. Decoding must ignore them.
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
