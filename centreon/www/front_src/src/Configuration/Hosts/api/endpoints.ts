export const hostsListEndpoint = '/configuration/hosts';

export const getHostEndpoint = ({ id }: { id: number | string }): string =>
  `${hostsListEndpoint}/${id}`;
