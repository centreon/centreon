import { isEmpty } from 'ramda';

import { getFoundFields } from '@centreon/ui';

import { searchableFields } from '../../testUtils';
import { CriteriaValue } from '../../Filter/Criterias/models';
import { searchableFieldsForPerformance } from '../../Filter/Criterias/searchQueryLanguage/models';

import { Search } from './models';

interface GetSearchProps {
  isResourceStatusFullSearchEnabled?: boolean;
  searchCriteria?: CriteriaValue;
}

export const getSearch = ({
  searchCriteria,
  isResourceStatusFullSearchEnabled
}: GetSearchProps): Search | undefined => {
  if (!searchCriteria) {
    return undefined;
  }

  const fieldMatches = getFoundFields({
    fields: searchableFields,
    value: searchCriteria as string
  });

  if (!isEmpty(fieldMatches)) {
    const matches = fieldMatches.map((item) => {
      const field = item?.field;
      const values = item.value?.split(',')?.join('|');

      return { field, value: `${field}:${values}` };
    });

    const formattedValue = matches.reduce((accumulator, previousValue) => {
      return {
        ...accumulator,
        value: `${accumulator.value} ${previousValue.value}`
      };
    });

    return {
      regex: {
        fields: matches.map(({ field }) => field),
        value: formattedValue.value
      }
    };
  }

  return {
    regex: {
      fields: !isResourceStatusFullSearchEnabled
        ? searchableFieldsForPerformance
        : searchableFields,
      value: searchCriteria as string
    }
  };
};
