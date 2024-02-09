import { useRef, useMemo, useEffect } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, head } from 'ramda';

import { SearchMatch, SearchParameter, getFoundFields } from '@centreon/ui';

import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';

const useSearch = (data: string) => {
  const [search, setSearch] = useAtom(searchAtom);

  const newSearch = useRef('');

  const fieldDelimiter = ':';
  const valueDelimiter = ',';

  const matchFieldDelimiter = new RegExp(`\\w+${fieldDelimiter}\\w*`, 'g');

  const matchValueDelimiter = (expression): RegExp =>
    new RegExp(
      `(?<![^${valueDelimiter}])${expression}(?![^${valueDelimiter}])`,
      'g'
    );

  const matchSpecificWord = (word): RegExp =>
    new RegExp(`(?<=\\s|^)${word}(?=\\s|$)`, 'g');

  useMemo(() => {
    const wordsIncomingData = data.split(' ');
    wordsIncomingData.forEach((word) => {
      const wordsWithFieldDelimiter = word.match(matchFieldDelimiter);

      if (!wordsWithFieldDelimiter) {
        const matchedSimpleWord = search.match(matchSpecificWord(word));
        if (!isEmpty(matchedSimpleWord)) {
          return;
        }
        newSearch.current = newSearch.current.concat(' ', word);
      }
      const searchableFieldIncomingData = getFoundFields({
        fields: Object.values(Fields),
        value: word
      });

      if (isEmpty(searchableFieldIncomingData)) {
        newSearch.current = newSearch.current.concat(' ', word);

        return;
      }

      const matchedSearchData = getFoundFields({
        fields: [head(searchableFieldIncomingData).field],
        value: search
      });

      if (isEmpty(matchedSearchData)) {
        newSearch.current = newSearch.current.concat(' ', word);

        return;
      }

      const [incomingData] = searchableFieldIncomingData;
      const [searchData] = matchedSearchData;

      if (incomingData.value === searchData.value) {
        return;
      }
      const values = incomingData.value.split(',');

      values.forEach((value) => {
        const matchedValue = searchData.value.match(matchValueDelimiter(value));
        if (matchedValue) {
          return;
        }

        const newValues = searchData.value.concat(',', value);

        newSearch.current = search.replace(
          `${searchData.field}:${searchData.value}`,
          `${searchData.field}:${newValues}`
        );
      });
    });
  }, [data]);

  console.log({ newSearch });

  useEffect(() => {
    if (!newSearch.current) {
      return;
    }
    setSearch(newSearch.current);
  }, [newSearch.current]);
};

export default useSearch;
