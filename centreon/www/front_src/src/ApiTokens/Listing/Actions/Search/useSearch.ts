import { useEffect, useMemo, useRef } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { filter, isEmpty, isNil, join, map, pipe, split } from 'ramda';

import { getFoundFields, useLocaleDateTimeFormat } from '@centreon/ui';

import {
  creationDateAtom,
  creatorsAtom,
  expirationDateAtom,
  isRevokedAtom,
  usersAtom
} from '../Filter/atoms';
import { Fields } from '../Filter/models';

import { searchAtom } from './atoms';
import { fieldDelimiter } from './models';
import { adjustData } from './utils';

const useSearch = (): void => {
  const [search, setSearch] = useAtom(searchAtom);
  const users = useAtomValue(usersAtom);
  const creators = useAtomValue(creatorsAtom);
  const expirationDate = useAtomValue(expirationDateAtom);
  const creationDate = useAtomValue(creationDateAtom);
  const isRevoked = useAtomValue(isRevokedAtom);
  const { toIsoString } = useLocaleDateTimeFormat();

  const newSearch = useRef('');

  const matchFieldDelimiter = new RegExp(`\\w+${fieldDelimiter}\\w*`, 'g');

  const matchSpecificWord = (word): RegExp =>
    new RegExp(`(?<=\\s|^)${word}(?=\\s|$)`, 'g');

  const searchableFieldData = [
    { data: users, field: Fields.UserName },
    { data: creators, field: Fields.CreatorName },
    {
      data: !isNil(creationDate) ? adjustData(toIsoString(creationDate)) : [],
      field: Fields.CreationDate
    },
    {
      data: !isNil(expirationDate)
        ? adjustData(toIsoString(expirationDate))
        : [],
      field: Fields.ExpirationDate
    },
    {
      data: !isNil(isRevoked) ? adjustData(isRevoked) : [],
      field: Fields.IsRevoked
    }
  ];

  const constructData = ({ data, field }): string => {
    if (!isEmpty(data)) {
      return `${[field]}:${data.map(({ name }) => name).join(',')}`;
    }

    return '';
  };

  const getDeletedValues = ({ data, field }): null | string => {
    if (!isEmpty(data)) {
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
  };

  const clearEmptyFields = (input): string | null => {
    const fieldValueToDelete = pipe(
      map(getDeletedValues),
      filter(Boolean)
    )(input);

    const deleteFromInputSearch = (word): string => {
      return fieldValueToDelete.some((wordToDelete) => wordToDelete === word)
        ? ''
        : word;
    };

    const updatedSearch = pipe(
      split(' '),
      map(deleteFromInputSearch),
      filter(Boolean),
      join(' ')
    )(search);

    return !isEmpty(fieldValueToDelete) ? updatedSearch : null;
  };

  const buildData = (): string => {
    newSearch.current = search;

    return pipe(
      map(constructData),
      filter(Boolean),
      join(' ')
    )(searchableFieldData);
  };

  useMemo(() => {
    const updatedSearch = clearEmptyFields(searchableFieldData);

    const data = buildData();

    newSearch.current = updatedSearch ?? search;

    const wordsIncomingData = data.split(' ').filter((item) => item);

    if (isEmpty(wordsIncomingData)) {
      return;
    }
    wordsIncomingData.forEach((word) => {
      const wordsWithFieldDelimiter = word.match(matchFieldDelimiter);

      if (!wordsWithFieldDelimiter) {
        const matchedSimpleWord = newSearch.current.match(
          matchSpecificWord(word)
        );
        if (!isEmpty(matchedSimpleWord)) {
          return;
        }
        newSearch.current = newSearch.current.concat(
          newSearch.current ? ' ' : '',
          word
        );
      }
      const searchableFieldIncomingData = getFoundFields({
        fields: Object.values(Fields),
        value: word
      });

      if (isEmpty(searchableFieldIncomingData)) {
        newSearch.current = newSearch.current.concat(
          newSearch.current ? ' ' : '',
          word
        );

        return;
      }
      const [incomingData] = searchableFieldIncomingData;

      const matchedSearchData = getFoundFields({
        fields: [incomingData.field],
        value: newSearch.current
      });

      if (isEmpty(matchedSearchData)) {
        newSearch.current = newSearch.current.concat(
          newSearch.current ? ' ' : '',
          `${incomingData.field}:${incomingData.value}`
        );

        return;
      }

      const [searchData] = matchedSearchData;

      if (incomingData.value === searchData.value) {
        return;
      }

      newSearch.current = newSearch.current.replace(
        `${searchData.field}:${searchData.value}`,
        `${searchData.field}:${incomingData.value}`
      );
    });
  }, [users.length, creators.length, creationDate, expirationDate, isRevoked]);

  useEffect(() => {
    setSearch(newSearch.current);
  }, [newSearch.current]);
};

export default useSearch;
