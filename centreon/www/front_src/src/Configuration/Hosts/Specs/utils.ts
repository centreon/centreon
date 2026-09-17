/**
 * Shaped like the API Platform collection at ./api/configuration/hosts: a Hydra
 * envelope whose items come from `HostCollectionOutput` (`activated`, `poller`,
 * `templates`).
 *
 * Host 1 carries a null alias, host 0 an explicit one, and neither carries an
 * icon — `HostCollectionOutput` does not expose it yet (MON-208571). The
 * decoder must accept all three cases.
 */
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
