import { JsonDecoder } from 'ts.data.json';

import {
  Contact,
  DatasetFilter,
  GetResourceAccessRule,
  NamedEntity,
  ResourceTypeEnum
} from '../../models';

const datasetFilter: JsonDecoder.Decoder<DatasetFilter> =
  JsonDecoder.object<DatasetFilter>(
    {
      datasetFilter: JsonDecoder.oneOf(
        [JsonDecoder.isNull(null), JsonDecoder.lazy(() => datasetFilter)],
        'Dataset filter'
      ),
      resources: JsonDecoder.array(
        JsonDecoder.object<NamedEntity>(
          {
            id: JsonDecoder.number,
            name: JsonDecoder.string
          },
          'Resource'
        ),
        'Resources'
      ),
      resourceType: JsonDecoder.enumeration(ResourceTypeEnum, 'Resource type')
    },
    'Dataset filter',
    {
      datasetFilter: 'dataset_filter',
      resourceType: 'type'
    }
  );

const contactGroups = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact group'
);

const contacts = JsonDecoder.object<Contact>(
  {
    alias: JsonDecoder.string,
    id: JsonDecoder.number
  },
  'Contact'
);

export const resourceAccessRuleDecoder =
  JsonDecoder.object<GetResourceAccessRule>(
    {
      contactGroups: JsonDecoder.object(
        {
          all: JsonDecoder.boolean,
          values: JsonDecoder.array(contactGroups, 'Contact group values')
        },
        'Contact groups'
      ),
      contacts: JsonDecoder.object(
        {
          all: JsonDecoder.boolean,
          values: JsonDecoder.array(contacts, 'Contact values')
        },
        'Contacts'
      ),
      datasetFilters: JsonDecoder.array(datasetFilter, 'Datasets filters'),
      description: JsonDecoder.string,
      id: JsonDecoder.number,
      isActivated: JsonDecoder.boolean,
      name: JsonDecoder.string
    },
    'Resource access rule',
    {
      contactGroups: 'contact_groups',
      datasetFilters: 'dataset_filters',
      isActivated: 'is_enabled'
    }
  );
