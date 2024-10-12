import { equals } from 'ramda';

export const getStatusesEndpoint = (
  resourceType: 'host' | 'service' | 'business-view' | 'business-activity'
): string => {
  if (equals(resourceType, 'business-activity')) {
    return '/bam/monitoring/business-activities/indicators/statuses';
  }

  if (equals(resourceType, 'business-view')) {
    return '/bam/monitoring/business-activities/statuses';
  }

  return `/monitoring/${resourceType}s/status`;
};
