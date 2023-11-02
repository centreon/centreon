import { buildListingEndpoint } from '@centreon/ui';
import type {
  BuildListingEndpointParameters,
  ListingParameters
} from '@centreon/ui';

import { baseEndpoint } from '../../../api/endpoint';
import { monitoringEndpoint, resourcesEndpoint } from '../../api/endpoint';

const hostCategoriesEndpoint = `${monitoringEndpoint}/hosts/categories`;
const serviceCategoriesEndpoint = `${monitoringEndpoint}/services/categories`;
const hostgroupsEndpoint = `${monitoringEndpoint}/hostgroups`;
const serviceGroupsEndpoint = `${monitoringEndpoint}/servicegroups`;
const monitoringServersEndpoint = `${baseEndpoint}/monitoring/servers`;
const hostSeveritiesEndpoint = `${monitoringEndpoint}/severities/host`;
const serviceSeveritiesEndpoint = `${monitoringEndpoint}/severities/service`;

const buildHostsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: resourcesEndpoint,
    customQueryParameters: [{ name: 'types', value: ['host'] }],
    parameters
  });
};

const buildServicesEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: resourcesEndpoint,
    customQueryParameters: [{ name: 'types', value: ['service'] }],
    parameters
  });
};

const buildHostGroupsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: hostgroupsEndpoint,
    parameters
  });
};

const buildServiceGroupsEndpoint = (
  parameters: BuildListingEndpointParameters
): string => {
  return buildListingEndpoint({
    baseEndpoint: serviceGroupsEndpoint,
    parameters
  });
};

const buildMonitoringServersEndpoint = (
  parameters: BuildListingEndpointParameters
): string => {
  return buildListingEndpoint({
    baseEndpoint: monitoringServersEndpoint,
    parameters
  });
};

const buildHostCategoriesEndpoint = (
  parameters: BuildListingEndpointParameters
): string => {
  return buildListingEndpoint({
    baseEndpoint: hostCategoriesEndpoint,
    parameters
  });
};

const buildServiceCategoriesEndpoint = (
  parameters: BuildListingEndpointParameters
): string => {
  return buildListingEndpoint({
    baseEndpoint: serviceCategoriesEndpoint,
    parameters
  });
};
const buildHostServeritiesEndpoint = (parameters): string => {
  return buildListingEndpoint({
    baseEndpoint: hostSeveritiesEndpoint,
    parameters
  });
};

const buildServiceSeveritiesEndpoint = (parameters): string => {
  return buildListingEndpoint({
    baseEndpoint: serviceSeveritiesEndpoint,
    parameters
  });
};

export {
  buildHostCategoriesEndpoint,
  buildServiceCategoriesEndpoint,
  buildHostGroupsEndpoint,
  buildServiceGroupsEndpoint,
  buildMonitoringServersEndpoint,
  buildHostServeritiesEndpoint,
  buildServiceSeveritiesEndpoint,
  buildHostsEndpoint,
  buildServicesEndpoint
};
