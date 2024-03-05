import { useCallback, useMemo } from 'react';

import {
  pipe,
  equals,
  flatten,
  isEmpty,
  isNil,
  split,
  map,
  filter,
  join,
  reject
} from 'ramda';
import { useAtomValue } from 'jotai';

import { QueryParameter, SearchParameter, getFoundFields } from '@centreon/ui';

import {
  creationDateAtom,
  expirationDateAtom,
  isRevokedAtom
} from '../Filter/atoms';
import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';
import { convertToBoolean } from './utils';
import { ConstructQueryParameters } from './models';

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

  const handleCustomQueries = (input: string): string | null => {
    const hasCustomQueryField = getFoundFields({
      fields: customQueriesData.map(({ field }) => field),
      value: input
    });
    if (isEmpty(hasCustomQueryField)) {
      return input;
    }

    return null;
  };

  const excludeCustomQueriesFromSearchInput = (): string => {
    return pipe(
      split(' '),
      map(handleCustomQueries),
      filter(Boolean),
      join(' ')
    )(search);
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

  const constructQueryParameters = ({
    value,
    field
  }): ConstructQueryParameters => {
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
  };

  const queryParameters = useMemo(() => {
    const customQueryField = getFoundFields({
      fields: customQueriesData.map(({ field }) => field),
      value: search
    });

    const result = pipe(
      map(constructQueryParameters),
      reject(isNil)
    )(customQueryField);

    return isEmpty(result) ? null : (result as Array<QueryParameter>);
  }, [search]);

  return { getSearchParameters, queryParameters };
};

export default useBuildParameters;
