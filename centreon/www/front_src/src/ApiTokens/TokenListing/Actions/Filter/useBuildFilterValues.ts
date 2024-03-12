import { useEffect, useMemo, useRef } from 'react';

import dayjs from 'dayjs';
import { useAtom, useAtomValue } from 'jotai';
import { equals, isEmpty, isNil, last } from 'ramda';

import { getFoundFields, useDeepCompare } from '@centreon/ui';

import { PersonalInformation } from '../../models';
import { searchAtom } from '../Search/atoms';
import { convertToBoolean } from '../Search/utils';

import {
  creationDateAtom,
  creatorsAtom,
  expirationDateAtom,
  isRevokedAtom,
  usersAtom
} from './atoms';
import { Fields } from './models';

const useBuildFilterValues = (): void => {
  const [users, setUsers] = useAtom(usersAtom);
  const [creators, setCreators] = useAtom(creatorsAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);
  const search = useAtomValue(searchAtom);

  const creatorsRef = useRef(creators);
  const usersRef = useRef(users);
  const expirationDateRef = useRef(expirationDate);
  const creationDateRef = useRef(creationDate);
  const isRevokedRef = useRef(isRevoked);

  const defaultFields = [
    {
      data: creationDate,
      field: Fields.CreationDate,
      initialValue: null,
      update: (data): void => {
        creationDateRef.current = data;
      }
    },
    {
      data: isRevoked,
      field: Fields.IsRevoked,
      initialValue: null,
      update: (data): void => {
        isRevokedRef.current = data;
      }
    },
    {
      data: expirationDate,
      field: Fields.ExpirationDate,
      initialValue: null,
      update: (data): void => {
        expirationDateRef.current = data;
      }
    },
    {
      data: users,
      field: Fields.UserName,
      initialValue: [],
      update: (data): void => {
        usersRef.current = data;
      }
    },
    {
      data: creators,
      field: Fields.CreatorName,
      initialValue: [],
      update: (data): void => {
        creatorsRef.current = data;
      }
    }
  ];

  const currentFullFields = useMemo(() => {
    return defaultFields
      .map(({ data, field }) => (!isNil(data) && !isEmpty(data) ? field : null))
      .filter((item) => item);
  }, [
    creationDateRef?.current,
    isRevokedRef?.current,
    expirationDateRef?.current,
    usersRef?.current?.length,
    creatorsRef?.current?.length
  ]);

  const constructData = ({
    value,
    dataToUpdate
  }): Array<PersonalInformation> => {
    const data = dataToUpdate.map(({ name }) => name);
    const newData = value
      .split(',')
      .map((simpleValue) => {
        return data.includes(simpleValue)
          ? dataToUpdate.find(({ name }) => name === simpleValue)
          : { id: crypto.randomUUID(), name: simpleValue };
      })
      .filter((item) => item);

    return [...newData];
  };

  const constructDataCustomQueries = ({ value }): string => {
    return last(value.split(','));
  };

  const initializeFullFields = (searchableField): void => {
    const fieldsToInitialize = currentFullFields
      .map((item) => {
        return searchableField.every(({ field }) => item !== field)
          ? item
          : null;
      })
      .filter((item) => item);

    defaultFields.forEach(({ field, update, initialValue }) => {
      fieldsToInitialize.forEach((item) => {
        if (item !== field) {
          return;
        }
        update(initialValue);
      });
    });
  };

  const updateContentFields = (searchableField): void => {
    searchableField.forEach(({ field, value }) => {
      if (equals(Fields.CreatorName, field)) {
        const updatedCreators = constructData({
          dataToUpdate: creators,
          value
        });
        creatorsRef.current = updatedCreators;
      }

      if (equals(Fields.UserName, field)) {
        const updatedUsers = constructData({ dataToUpdate: users, value });
        usersRef.current = updatedUsers;
      }
      if (equals(Fields.ExpirationDate, field)) {
        const updatedExpirationDate = dayjs(
          constructDataCustomQueries({ value })
        ).toDate();
        expirationDateRef.current = updatedExpirationDate;
      }
      if (equals(Fields.CreationDate, field)) {
        const updatedCreationDate = dayjs(
          constructDataCustomQueries({ value })
        ).toDate();
        creationDateRef.current = updatedCreationDate;
      }
      if (equals(Fields.IsRevoked, field)) {
        const updatedIsRevoked = constructDataCustomQueries({ value });

        isRevokedRef.current = convertToBoolean(updatedIsRevoked);
      }
    });
  };

  useMemo(() => {
    const searchableFieldInSearchInput = getFoundFields({
      fields: Object.values(Fields),
      value: search
    });

    if (isEmpty(searchableFieldInSearchInput)) {
      initializeFullFields(searchableFieldInSearchInput);

      return;
    }

    initializeFullFields(searchableFieldInSearchInput);

    updateContentFields(searchableFieldInSearchInput);
  }, [search]);

  useEffect(
    () => {
      setUsers(usersRef.current);
      setCreators(creatorsRef.current);
      setIsRevoked(isRevokedRef.current);
      setCreationDate(creationDateRef.current);
      setExpirationDate(expirationDateRef.current);
    },
    useDeepCompare([
      usersRef.current,
      creatorsRef.current,
      isRevokedRef.current,
      expirationDateRef.current,
      creationDateRef.current
    ])
  );
};

export default useBuildFilterValues;
