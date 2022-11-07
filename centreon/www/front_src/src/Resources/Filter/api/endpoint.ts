import {
  buildListingEndpoint,
  BuildListingEndpointParameters,
  ListingParameters,
} from '@centreon/ui';

<<<<<<< HEAD
import { baseEndpoint } from '../../../api/endpoint';
import { monitoringEndpoint } from '../../api/endpoint';
=======
import { baseEndpoint, monitoringEndpoint } from '../../api/endpoint';
>>>>>>> centreon/dev-21.10.x

const hostgroupsEndpoint = `${monitoringEndpoint}/hostgroups`;
const serviceGroupsEndpoint = `${monitoringEndpoint}/servicegroups`;
const monitoringServersEndpoint = `${baseEndpoint}/monitoring/servers`;

const buildHostGroupsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: hostgroupsEndpoint,
    parameters,
  });
};

const buildServiceGroupsEndpoint = (
  parameters: BuildListingEndpointParameters,
): string => {
  return buildListingEndpoint({
    baseEndpoint: serviceGroupsEndpoint,
    parameters,
  });
};

const buildMonitoringServersEndpoint = (
  parameters: BuildListingEndpointParameters,
): string => {
  return buildListingEndpoint({
    baseEndpoint: monitoringServersEndpoint,
    parameters,
  });
};

export {
  buildHostGroupsEndpoint,
  buildServiceGroupsEndpoint,
  buildMonitoringServersEndpoint,
};
