export const getStatusesEndpoint = (resourceType: 'host' | 'service'): string =>
  `/monitoring/${resourceType}s/status`;
