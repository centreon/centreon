export const getEndpoint = (resourceType: string): string =>
  `/monitoring/${resourceType.replace('-', '')}s`;
