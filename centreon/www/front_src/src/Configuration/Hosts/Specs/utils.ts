/**
 * Shaped like the endpoint currently served at /api/latest/configuration/hosts:
 * the legacy `{ result, meta }` envelope with `monitoring_server` and
 * `is_activated`. See api/decoders.ts for the switch to API Platform.
 *
 * Host 1 carries a null alias and no icon key, so the decoder is exercised
 * against both an explicit null and an omitted field.
 */
export const getListingResponse = () => ({
  meta: {
    limit: 10,
    page: 1,
    total: 2
  },
  result: [
    {
      address: '10.0.0.0',
      alias: 'alias for host 0',
      icon: {
        id: 1,
        name: 'server.png',
        url: '/img/media/icons/server.png'
      },
      id: 0,
      is_activated: true,
      monitoring_server: { id: 1, name: 'Central' },
      name: 'host 0',
      templates: [{ id: 5, name: 'generic-active-host' }]
    },
    {
      address: '10.0.0.1',
      alias: null,
      id: 1,
      is_activated: false,
      monitoring_server: { id: 1, name: 'Central' },
      name: 'host 1',
      templates: []
    }
  ]
});

export const emptyListingResponse = {
  meta: {
    limit: 10,
    page: 1,
    total: 0
  },
  result: []
};
