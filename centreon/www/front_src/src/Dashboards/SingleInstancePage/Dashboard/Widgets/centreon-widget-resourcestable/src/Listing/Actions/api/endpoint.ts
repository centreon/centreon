const resourcesEndpoint = './api/latest/monitoring/resources';

const acknowledgeEndpoint = `${resourcesEndpoint}/acknowledge`;
const downtimeEndpoint = `${resourcesEndpoint}/downtime`;
const checkEndpoint = `${resourcesEndpoint}/check`;

export { acknowledgeEndpoint, downtimeEndpoint, checkEndpoint };
