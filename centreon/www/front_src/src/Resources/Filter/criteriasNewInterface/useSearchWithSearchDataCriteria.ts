import { useEffect, useState } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, isNil, not, pipe } from 'ramda';

import { getFoundFields } from '@centreon/ui';

import { Criteria } from '../Criterias/models';
import { currentFilterAtom, searchAtom } from '../filterAtoms';

import { escapeRegExpSpecialChars } from './utils';

interface Parameters {
  selectableCriterias: Array<Criteria>;
}
interface CustomSearchedFields {
  content: Array<string>;
  field: string;
}

interface UpdatedSearchInput {
  updatedSearch: string;
}

const useSearchWihSearchDataCriteria = ({
  selectableCriterias
}: Parameters): void => {
  const [inputSearch, setInputSearch] = useState('');
  const [search, setSearch] = useAtom(searchAtom);
  const currentFilter = useAtomValue(currentFilterAtom);

  const getBuiltCustomSearchedFields = (): CustomSearchedFields => {
    return selectableCriterias
      .filter(pipe(({ search_data }) => search_data, isNil, not))
      .map(({ search_data }) => {
        const formattedCustomSearchedFields = search_data?.values?.map(
          ({ value }) => value
        );

        return {
          content: formattedCustomSearchedFields,
          field: search_data?.field
        };
      });
  };

  const updatedSearchInput = (): UpdatedSearchInput | string => {
    const data = getBuiltCustomSearchedFields();

    return data.reduce(
      (accumulator, currentValue) => {
        const { content } = currentValue;
        const { field } = currentValue;
        const customContent = content.map((item) =>
          escapeRegExpSpecialChars(item)
        );
        const target = `${field}:${customContent?.join(',')}`;

        const fieldInSearchInput = `${field}:`;
        const { updatedSearch } = accumulator;

        if (!isEmpty(customContent)) {
          if (search?.includes(fieldInSearchInput)) {
            const result = getFoundFields({ fields: [field], value: search });
            const formattedResult = `${result[0].field}:${result[0].value}`;

            const valuesInSearchInput = result[0].value?.split(',');
            const updatedValues = Array.from(
              new Set([...valuesInSearchInput, ...customContent])
            );

            const newTarget = `${field}:${updatedValues.join(',')}`;

            const newSearch = updatedSearch || search;

            return {
              ...accumulator,
              updatedSearch: newSearch?.replace(formattedResult, newTarget)
            };
          }

          return !updatedSearch
            ? { ...accumulator, updatedSearch: search.concat(' ', target) }
            : { ...accumulator };
        }

        return search;
      },
      { updatedSearch: '' }
    );
  };

  useEffect(() => {
    const value = updatedSearchInput()?.updatedSearch;
    const newSearch = isNil(value) || isEmpty(value) ? search : value;
    const result = newSearch.replace(/\s+/g, ' ');
    setInputSearch(result);
  }, [currentFilter]);

  useEffect(() => {
    setSearch(inputSearch);
  }, [inputSearch]);
};

export default useSearchWihSearchDataCriteria;
