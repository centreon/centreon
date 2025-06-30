import { getFoundFields } from '@centreon/ui';
import { isEmpty } from 'ramda';
import { searchableFields } from '../../testUtils';
import { Search } from './models';
import { searchableFieldsForPerformance } from '../../Filter/Criterias/searchQueryLanguage/models';
import { CriteriaValue } from '../../Filter/Criterias/models';

interface GetSearchProps {
  searchCriteria?: CriteriaValue;
  isResourceStatusFullSearchEnabled?: boolean;
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
