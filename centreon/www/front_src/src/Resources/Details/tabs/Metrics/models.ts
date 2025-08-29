import type { ListingModel } from '@centreon/ui';

import { Resource } from '../../../models';

export interface MetaServiceMetric {
  id: number;
  name: string;
  resource: Omit<Resource, 'uuid'>;
  unit: string;
  value: number;
}

export type MetaServiceMetricListing = ListingModel<MetaServiceMetric>;
