import { JsonDecoder } from 'ts.data.json';

import { Dataset, NamedEntity, ResourceAccessRule } from '../../models';

const contactGroup = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact group'
);

const contact = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact'
);

const dataset = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Dataset'
);

const datasets = JsonDecoder.object<Dataset>(
  {
    resourceType: JsonDecoder.string,
    resources: JsonDecoder.array(dataset, 'Dataset')
  },
  'Datasets',
  {
    resourceType: 'type'
  }
);

const datasetsFilters = JsonDecoder.array(datasets, 'Dataset filters');

export const resourceAccessRuleDecoder = JsonDecoder.object<ResourceAccessRule>(
  {
    contactGroups: JsonDecoder.array(contactGroup, 'Contact groups'),
    contacts: JsonDecoder.array(contact, 'Contacts'),
    datasets: JsonDecoder.array(datasetsFilters, 'Datasets filters'),
    description: JsonDecoder.string,
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string
  },
  'Resource access rule',
  {
    datasets: 'datasets_filters',
    isActivated: 'is_enabled'
  }
);
