import { useEffect, useState } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, isNil, not, pipe } from 'ramda';

import { getFoundFields } from '@centreon/ui';

import { Criteria } from '../Criterias/models';
import { currentFilterAtom, searchAtom } from '../filterAtoms';

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
      .filter(pipe(({ searchData }) => searchData, isNil, not))
      .map(({ searchData }) => {
        const formattedCustomSearchedFields = searchData?.values?.map(
          ({ value }) => value
        );

        return {
          content: formattedCustomSearchedFields,
          field: searchData?.field
        };
      });
  };

  const updatedSearchInput = (): UpdatedSearchInput | string => {
    const data = getBuiltCustomSearchedFields();

    return data.reduce(
      (accumulator, currentValue) => {
        const { content } = currentValue;
        const { field } = currentValue;
        const target = `${field}:${content?.join(',')}`;

        const fieldInSearchInput = `${field}:`;
        const { updatedSearch } = accumulator;

        if (!isEmpty(content)) {
          if (search?.includes(fieldInSearchInput)) {
            const result = getFoundFields({ fields: [field], value: search });
            const formattedResult = `${result[0].field}:${result[0].value}`;

            const newSearch = updatedSearch || search;

            return {
              ...accumulator,
              updatedSearch: newSearch?.replace(formattedResult, target)
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
