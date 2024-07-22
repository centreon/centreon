export const additionalConnectorsEndpoint =
  'http://localhost:3000/api/latest/configuration/additional-connectors';

export const getPollersEndpoint = 'http://localhost:3000/api/latest/pollers';
export const getConnectorTypesEndpoint =
  'http://localhost:3000/api/latest/connector-types';

export const getAdditionalConnectorEndpoint = (id): string =>
  `http://localhost:3000/api/latest/configuration/additional-connector/${id}`;
