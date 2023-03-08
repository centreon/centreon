import { baseEndpoint } from '../../api/endpoint';

export const loginEndpoint = `${baseEndpoint}/authentication/providers/configurations/local`;
export const providersConfigurationEndpoint = `${baseEndpoint}/authentication/providers/configurations`;
export const loginConfigurationEndpoints = `http://localhost:3000/centreon/api/latest/platform/customization/configuration/login`;
export const loginConfiguration404 =
  'http://localhost:3000/centreon/api/latest/platform/customization/configuration/login404';
