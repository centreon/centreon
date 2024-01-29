import { JsonDecoder } from 'ts.data.json';

import { Dataset, ResourceAccessRule, ResourceTypeEnum } from '../../models';

const datasets = JsonDecoder.object<Dataset>(
  {
    resourceType: JsonDecoder.enumeration(ResourceTypeEnum, 'resourceType'),
    resources: JsonDecoder.array(JsonDecoder.number, 'Dataset')
  },
  'Datasets',
  {
    resourceType: 'type'
  }
);

const datasetsFilters = JsonDecoder.array(datasets, 'Dataset filters');

export const resourceAccessRuleDecoder = JsonDecoder.object<ResourceAccessRule>(
  {
    contactGroups: JsonDecoder.array(JsonDecoder.number, 'Contact groups'),
    contacts: JsonDecoder.array(JsonDecoder.number, 'Contacts'),
    datasetFilters: JsonDecoder.array(datasetsFilters, 'Datasets filters'),
    description: JsonDecoder.string,
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string
  },
  'Resource access rule',
  {
    datasetFilters: 'datasets_filters',
    isActivated: 'is_enabled'
  }
);
