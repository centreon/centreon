import { flatten } from 'ramda';

import { SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../../Filter/models';

export const buildSearchParameters = (
  searchValue: string
): (() => SearchParameter) => {
  const fieldMatches = getFoundFields({
    fields: [...Object.values(Fields)],
    value: searchValue
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

  const getSearchParameters = (): SearchParameter => {
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
        value: searchValue
      }
    };
  };

  return getSearchParameters;
};
