 const graphEndpoint =
  './api/latest/monitoring/dashboard/metrics/performances/data';


export const graphEndpointt =({hostId, serviceId, metricName})=>`./api/latest/monitoring/hosts/${hostId}/services/${serviceId}/metrics/${metricName}`;

