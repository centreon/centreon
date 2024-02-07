import { SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from './Filter/models';

interface UseBuildSearchParameters {
  getSearchParameters: () => SearchParameter;
}

const useBuildSearchParameters = (
  searchValue: string
): UseBuildSearchParameters => {
  const fieldMatches = getFoundFields({
    fields: [...Object.values(Fields)],
    value: searchValue
  });

  const terms = fieldMatches.map(({ field, value }) => {
    const values = value.split(',');
    if (values?.length <= 1) {
      return { field, value };
    }

    return { field, value: values };
  });

  const getSearchParameters = (): SearchParameter => {
    const hasMultipleSearch = terms.some((term) => Array.isArray(term?.value));

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

  return { getSearchParameters };
};

export default useBuildSearchParameters;
