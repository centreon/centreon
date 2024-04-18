import { JsonDecoder } from 'ts.data.json';

import {
  GetResourceAccessRule,
  NamedEntity,
  ResourceTypeEnum,
  DatasetFilter
} from '../../models';

const datasetFilter: JsonDecoder.Decoder<DatasetFilter> =
  JsonDecoder.object<DatasetFilter>(
    {
      datasetFilter: JsonDecoder.oneOf(
        [JsonDecoder.isNull(null), JsonDecoder.lazy(() => datasetFilter)],
        'Dataset filter'
      ),
      resourceType: JsonDecoder.enumeration(ResourceTypeEnum, 'Resource type'),
      resources: JsonDecoder.array(
        JsonDecoder.object<NamedEntity>(
          {
            id: JsonDecoder.number,
            name: JsonDecoder.string
          },
          'Resource'
        ),
        'Resources'
      )
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

const contacts = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact'
);

export const resourceAccessRuleDecoder =
  JsonDecoder.object<GetResourceAccessRule>(
    {
      allContactGroups: JsonDecoder.boolean,
      allContacts: JsonDecoder.boolean,
      contactGroups: JsonDecoder.array(contactGroups, 'Contact groups'),
      contacts: JsonDecoder.array(contacts, 'Contacts'),
      datasetFilters: JsonDecoder.array(datasetFilter, 'Datasets filters'),
      description: JsonDecoder.string,
      id: JsonDecoder.number,
      isActivated: JsonDecoder.boolean,
      name: JsonDecoder.string
    },
    'Resource access rule',
    {
      allContactGroups: 'all_contact_groups',
      allContacts: 'all_contacts',
      contactGroups: 'contact_groups',
      datasetFilters: 'dataset_filters',
      isActivated: 'is_enabled'
    }
  );
