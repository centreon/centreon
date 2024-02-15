import { equals, flatten } from 'ramda';

import { SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../../Filter/models';
import { PersonalInformation } from '../../../models';

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

export const getUniqData = (data): Array<PersonalInformation> => {
  const result = [
    ...new Map(data.map((item) => [item.name, item])).values()
  ] as Array<PersonalInformation>;

  return result || [];
};

export const adjustData = (value) => {
  return [{ id: 0, name: value }];
};

export const convertToBoolean = (input: string) => {
  return input === 'true';
};
