export const getListingResponse = () => ({
  '@context': '/centreon/api/contexts/Host',
  '@id': '/centreon/api/configuration/hosts',
  '@type': 'Collection',
  member: [
    {
      activated: true,
      address: '10.0.0.0',
      alias: 'alias for host 0',
      id: 0,
      name: 'host 0',
      poller: { id: 1, name: 'Central' },
      templates: [{ id: 5, name: 'generic-active-host' }]
    },
    {
      activated: false,
      address: '10.0.0.1',
      alias: null,
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
