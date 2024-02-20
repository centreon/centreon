import { useCallback, useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, flatten, isEmpty, isNil } from 'ramda';

import {
  QueryParameter,
  SearchParameter,
  getFoundFields,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import {
  creationDateAtom,
  expirationDateAtom,
  isRevokedAtom
} from '../Filter/atoms';
import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';

interface UseButtonParameters {
  getSearchParameters: () => SearchParameter;
  queryParameters: Array<QueryParameter> | null;
}

const useBuildParameters = (): UseButtonParameters => {
  const search = useAtomValue(searchAtom);
  const creationDate = useAtomValue(creationDateAtom);
  const expirationDate = useAtomValue(expirationDateAtom);
  const isRevoked = useAtomValue(isRevokedAtom);
  const { toIsoString } = useLocaleDateTimeFormat();

  const customQueriesData = [
    { data: creationDate, field: Fields.CreationDate },
    { data: expirationDate, field: Fields.ExpirationDate },
    { data: isRevoked, field: Fields.IsRevoked }
  ];

  const getSearchParameters = useCallback(() => {
    // const updatedSearch = clearFields({ input: customQueriesData, search });

    // console.log({ search, updatedSearch });

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
        value: search
      }
    };
  }, [search]);

  const queryParameters = useMemo(() => {
    const result = customQueriesData
      .map(({ data, field }) => {
        if (isNil(data)) {
          return null;
        }

        const value = equals(typeof data, 'boolean')
          ? (data as boolean)
          : toIsoString(data as Date);

        return {
          name: field as string,
          value
        };
      })
      .filter((item) => !isNil(item));

    return isEmpty(result) ? null : (result as Array<QueryParameter>);
  }, [creationDate, expirationDate, isRevoked]);

  return { getSearchParameters, queryParameters };
};

export default useBuildParameters;
