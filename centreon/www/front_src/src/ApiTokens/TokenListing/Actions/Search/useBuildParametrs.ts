import { useCallback, useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, flatten, isEmpty, isNil } from 'ramda';

import { QueryParameter, SearchParameter, getFoundFields } from '@centreon/ui';

import {
  creationDateAtom,
  expirationDateAtom,
  isRevokedAtom
} from '../Filter/atoms';
import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';
import { convertToBoolean } from './utils';

interface UseButtonParameters {
  getSearchParameters: () => SearchParameter | undefined;
  queryParameters: Array<QueryParameter> | null;
}

const useBuildParameters = (): UseButtonParameters => {
  const search = useAtomValue(searchAtom);
  const creationDate = useAtomValue(creationDateAtom);
  const expirationDate = useAtomValue(expirationDateAtom);
  const isRevoked = useAtomValue(isRevokedAtom);

  const customQueriesData = [
    { data: creationDate, field: Fields.CreationDate },
    { data: expirationDate, field: Fields.ExpirationDate },
    { data: isRevoked, field: Fields.IsRevoked }
  ];

  const excludeCustomQueriesFromSearchInput = (): string => {
    return search
      .split(' ')
      .map((item) => {
        const hasCustomQueryField = getFoundFields({
          fields: customQueriesData.map(({ field }) => field),
          value: item
        });
        if (isEmpty(hasCustomQueryField)) {
          return item;
        }

        return null;
      })
      .filter((item) => item)
      .join(' ');
  };

  const getSearchParameters = useCallback(() => {
    const updatedSearch = excludeCustomQueriesFromSearchInput();

    if (!updatedSearch) {
      return undefined;
    }

    const fieldMatches = getFoundFields({
      fields: [...Object.values(Fields)],
      value: updatedSearch
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
    const hasMultipleSearch =
      [...new Map(terms.map((term) => [term.field, term])).values()].length !==
      terms?.length;

    if (hasMultipleSearch) {
      return {
        conditions: terms.map((term) => ({
          field: term.field,
          values: { $rg: term.value }
        }))
      };
    }

    return {
      regex: {
        fields: [...Object.values(Fields)],
        value: updatedSearch
      }
    };
  }, [search]);

  const queryParameters = useMemo(() => {
    const customQueryField = getFoundFields({
      fields: customQueriesData.map(({ field }) => field),
      value: search
    });

    const result = customQueryField
      .map(({ value, field }) => {
        if (isNil(value)) {
          return null;
        }

        const newValue = equals(field, Fields.IsRevoked)
          ? convertToBoolean(value)
          : value;

        return {
          name: field as string,
          value: newValue
        };
      })
      .filter((item) => !isNil(item));

    return isEmpty(result) ? null : (result as Array<QueryParameter>);
  }, [search]);

  return { getSearchParameters, queryParameters };
};

export default useBuildParameters;
