import { useCallback } from 'react';

import { useAtomValue } from 'jotai';
import { equals, flatten, isEmpty } from 'ramda';

import { SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';
import { convertToBoolean } from './utils';

interface UseBuildParameters {
  getSearchParameters: () => SearchParameter | undefined;
}

const useBuildParameters = (): UseBuildParameters => {
  const search = useAtomValue(searchAtom);

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

    const getValues = ({ field, value }): Record<string, string | boolean> => {
      if (equals(field, Fields.CreationDate)) {
        return { $ge: value };
      }
      if (equals(field, Fields.ExpirationDate)) {
        return { $le: value };
      }
      if (equals(field, Fields.IsRevoked)) {
        return { $eq: convertToBoolean(value) };
      }

      return { $rg: value };
    };

    if (isEmpty(terms)) {
      return {
        regex: {
          fields: [...Object.values(Fields)],
          value: search
        }
      };
    }

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
