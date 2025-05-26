import { resourcesEndpoint } from '../../api/endpoint';

const acknowledgeEndpoint = `${resourcesEndpoint}/acknowledge`;
const downtimeEndpoint = `${resourcesEndpoint}/downtime`;
const checkEndpoint = `${resourcesEndpoint}/check`;
const commentEndpoint = `${resourcesEndpoint}/comments`;
const csvExportEndpoint = `${resourcesEndpoint}/export`;

export {
  acknowledgeEndpoint,
  downtimeEndpoint,
  checkEndpoint,
  commentEndpoint,
  csvExportEndpoint
};
