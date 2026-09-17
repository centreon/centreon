/**
 * The hosts listing endpoint is API Platform, so responses are Hydra-shaped
 * (`member` / `totalItems`) rather than `{ result, meta }`.
 *
 * `skip_null_values` is applied, so a field with no value is ABSENT from the
 * payload rather than null. Host 1 below deliberately omits `alias` and `icon`
 * to exercise that.
 */
export const getListingResponse = () => ({
  '@context': '/api/contexts/Host',
  '@id': '/api/configuration/hosts',
  '@type': 'hydra:Collection',
  member: [
    {
      '@id': '/api/configuration/hosts/0',
      '@type': 'Host',
      activated: true,
      address: '10.0.0.0',
      alias: 'alias for host 0',
      icon: {
        id: 1,
        name: 'server.png',
        url: '/img/media/icons/server.png'
      },
      id: 0,
      name: 'host 0',
      poller: { id: 1, name: 'Central' },
      templates: [{ id: 5, name: 'generic-active-host' }]
    },
    {
      '@id': '/api/configuration/hosts/1',
      '@type': 'Host',
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
  '@context': '/api/contexts/Host',
  '@id': '/api/configuration/hosts',
  '@type': 'hydra:Collection',
  member: [],
  totalItems: 0
};
