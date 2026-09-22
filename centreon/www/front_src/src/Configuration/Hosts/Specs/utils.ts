import hostIcon from './assets/host-icon.jpg';

export { hostIcon };

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
    }
  ],
  totalItems: 2
});

export const emptyListingResponse = {
  '@context': '/centreon/api/contexts/Host',
  '@id': '/centreon/api/configuration/hosts',
  '@type': 'Collection',
  member: [],
  totalItems: 0
};
