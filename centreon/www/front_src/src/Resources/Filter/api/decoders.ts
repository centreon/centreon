import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import { Filter } from '../models';
import { Criteria, SearchData, SearchedDataValue } from '../Criterias/models';
import { SortOrder } from '../../models';

const searchDataValueDecoder = JsonDecoder.object<SearchedDataValue>(
  {
    id: JsonDecoder.string,
    value: JsonDecoder.string,
    valueId: JsonDecoder.number
  },
  'searchedDataValue'
);

const searchDataDecoder = JsonDecoder.object<SearchData>(
  {
    field: JsonDecoder.string,
    id: JsonDecoder.string,
    type: JsonDecoder.string,
    values: JsonDecoder.array(searchDataValueDecoder, 'searchedDataValues')
  },
  'searchData'
);

const entityDecoder = JsonDecoder.object<Filter>(
  {
    criterias: JsonDecoder.array<Criteria>(
      JsonDecoder.object<Criteria>(
        {
          name: JsonDecoder.string,
          object_type: JsonDecoder.nullable(JsonDecoder.string),
          type: JsonDecoder.string,
          value: JsonDecoder.optional(
            JsonDecoder.oneOf<
              | string
              | Array<Pick<SelectEntry, 'id' | 'name'>>
              | [string, SortOrder, ...Array<string | SortOrder>]
            >(
              [
                JsonDecoder.string,
                JsonDecoder.array(JsonDecoder.string, 'MultiSort'),
                JsonDecoder.array<Pick<SelectEntry, 'id' | 'name'>>(
                  JsonDecoder.object<Pick<SelectEntry, 'id' | 'name'>>(
                    {
                      id: JsonDecoder.oneOf<number | string>(
                        [JsonDecoder.number, JsonDecoder.string],
                        'FilterCriteriaMultiSelectId'
                      ),
                      name: JsonDecoder.string
                    },
                    'FilterCriteriaMultiSelectValue'
                  ),
                  'FilterCriteriaValues'
                ),
                JsonDecoder.tuple(
                  [
                    JsonDecoder.string,
                    JsonDecoder.enumeration<SortOrder>(
                      SortOrder,
                      'FilterCriteriaSortOrder'
                    )
                  ],
                  'FilterCriteriaTuple'
                )
              ],
              'FilterCriteriaValue'
            )
          )
        },
        'FilterCriterias'
      ),
      'FilterCriterias'
    ),
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'CustomFilter'
);

const listCustomFiltersDecoder = buildListingDecoder<Filter>({
  entityDecoder,
  entityDecoderName: 'CustomFilter',
  listingDecoderName: 'CustomFilters'
});

export { listCustomFiltersDecoder };
