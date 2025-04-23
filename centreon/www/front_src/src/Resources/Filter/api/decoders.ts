import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import { SortOrder } from '../../models';
import { Criteria } from '../Criterias/models';
import { Filter } from '../models';

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
