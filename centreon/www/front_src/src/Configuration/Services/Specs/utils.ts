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
