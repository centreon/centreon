import { useCallback } from 'react';

import { useAtomValue } from 'jotai';
import { equals, flatten } from 'ramda';

import { SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';
import { convertToBoolean } from './utils';

interface UseBuildParameters {
  getSearchParameters: () => SearchParameter | undefined;
}

const useBuildParameters = (): UseBuildParameters => {
  const search = useAtomValue(searchAtom);

  const customFields = [
    Fields.CreationDate,
    Fields.ExpirationDate,
    Fields.IsRevoked
  ];

  const getSearchParameters = useCallback(() => {
    if (!search) {
      return undefined;
    }

    const fieldMatches = getFoundFields({
      fields: [...Object.values(Fields)],
      value: search
    });

    const searchedWords = fieldMatches.map(({ field, value }) => {
      const values = value.split(',');
      if (values?.length <= 1) {
        return { field, value };
      }

      return values.map((item) => ({
        field,
        value: item
      }));
    });
    const terms = flatten(searchedWords);

    const getValues = ({ field, value }): Record<string, string | boolean> =>
      customFields.includes(field)
        ? {
            $eq: !equals(field, Fields.IsRevoked)
              ? value
              : convertToBoolean(value)
          }
        : { $rg: value };

    return {
      conditions: terms.map(({ field, value }) => ({
        field,
        values: getValues({ field, value })
      }))
    };
  }, [search]);

  return { getSearchParameters };
};

export default useBuildParameters;
