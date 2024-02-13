import { useRef, useMemo, useEffect, useId } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, head, equals } from 'ramda';

import { SearchMatch, SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../Filter/models';
import { creatorsAtom, usersAtom } from '../Filter/atoms';

import { searchAtom } from './atoms';
import { fieldDelimiter, valueDelimiter } from './models';
import { getUniqData } from './utils';

const useSearch = () => {
  const [search, setSearch] = useAtom(searchAtom);
  const [users, setUsers] = useAtom(usersAtom);
  const [creators, setCreators] = useAtom(creatorsAtom);
  const id = useId();

  const newSearch = useRef('');

  const matchFieldDelimiter = new RegExp(`\\w+${fieldDelimiter}\\w*`, 'g');

  const matchValueDelimiter = (expression): RegExp =>
    new RegExp(
      `(?<![^${valueDelimiter}])${expression}(?![^${valueDelimiter}])`,
      'g'
    );

  const matchSpecificWord = (word): RegExp =>
    new RegExp(`(?<=\\s|^)${word}(?=\\s|$)`, 'g');

  const getDataPersonalInformation = ({ data, field }) => {
    if (!isEmpty(data)) {
      return `${[field]}:${data.map(({ name }) => name).join(',')}`;
    }

    return '';
  };

  const clearEmptyFields = (data) => {
    const wordsToDelete = data
      .map(({ items, field }) => {
        if (!isEmpty(items)) {
          return null;
        }

        const [searchData] = getFoundFields({
          fields: [field],
          value: search
        });

        if (!searchData) {
          return null;
        }

        return `${searchData?.field}:${searchData?.value}`;
      })
      .filter((item) => item);

    const updatedSearch = search
      .split(' ')
      .map((word) => {
        return wordsToDelete.every((wordToDelete) => wordToDelete === word)
          ? ''
          : word;
      })
      .join(' ');

    if (isEmpty(wordsToDelete)) {
      return;
    }
    newSearch.current = updatedSearch;
  };

  const buildData = () => {
    newSearch.current = search;
    const usersData = getDataPersonalInformation({
      data: users,
      field: Fields.UserName
    });

    const creatorsData = getDataPersonalInformation({
      data: creators,
      field: Fields.CreatorName
    });

    return usersData.concat(' ', creatorsData);
  };

  useMemo(() => {
    const data = buildData();
    const wordsIncomingData = data.split(' ').filter((item) => item);
    clearEmptyFields([
      { field: Fields.UserName, items: users },
      { field: Fields.CreatorName, items: creators }
    ]);

    if (isEmpty(wordsIncomingData)) {
      return;
    }
    wordsIncomingData.forEach((word) => {
      const wordsWithFieldDelimiter = word.match(matchFieldDelimiter);

      if (!wordsWithFieldDelimiter) {
        const matchedSimpleWord = search.match(matchSpecificWord(word));
        if (!isEmpty(matchedSimpleWord)) {
          return;
        }
        newSearch.current = newSearch.current.concat(search ? ' ' : '', word);
      }
      const searchableFieldIncomingData = getFoundFields({
        fields: Object.values(Fields),
        value: word
      });

      if (isEmpty(searchableFieldIncomingData)) {
        newSearch.current = newSearch.current.concat(search ? ' ' : '', word);

        return;
      }

      const matchedSearchData = getFoundFields({
        fields: [head(searchableFieldIncomingData).field],
        value: search
      });

      if (isEmpty(matchedSearchData)) {
        newSearch.current = newSearch.current.concat(search ? ' ' : '', word);

        return;
      }

      const [incomingData] = searchableFieldIncomingData;
      const [searchData] = matchedSearchData;

      if (incomingData.value === searchData.value) {
        return;
      }

      newSearch.current = search.replace(
        `${searchData.field}:${searchData.value}`,
        `${searchData.field}:${incomingData.value}`
      );
    });
  }, [users.length, creators.length]);

  useEffect(() => {
    setSearch(newSearch.current);
  }, [newSearch.current]);
};

export default useSearch;
