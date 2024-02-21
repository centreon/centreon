import { useEffect, useMemo, useRef } from 'react';

import dayjs from 'dayjs';
import { useAtom } from 'jotai';
import { equals, isEmpty, isNil } from 'ramda';

import { getFoundFields } from '@centreon/ui';

import { searchAtom } from '../Search/atoms';
import { PersonalInformation } from '../../models';
import { adjustData, convertToBoolean, getUniqData } from '../Search/utils';

import {
  creationDateAtom,
  creatorsAtom,
  expirationDateAtom,
  isRevokedAtom,
  usersAtom
} from './atoms';
import { Fields } from './models';
import useInitializeFilter from './useInitializeFilter';

const useBuildFilterValues = () => {
  const [search, setSearch] = useAtom(searchAtom);
  const [users, setUsers] = useAtom(usersAtom);
  const [creators, setCreators] = useAtom(creatorsAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);
  const { initialize } = useInitializeFilter();

  const defaultFields = [
    {
      data: creationDate,
      field: Fields.CreationDate,
      initialValue: null,
      update: setCreationDate
    },
    {
      data: isRevoked,
      field: Fields.IsRevoked,
      initialValue: null,
      update: setIsRevoked
    },
    {
      data: expirationDate,
      field: Fields.ExpirationDate,
      initialValue: null,
      update: setExpirationDate
    },
    { data: users, field: Fields.UserName, initialValue: [], update: setUsers },
    {
      data: creators,
      field: Fields.CreatorName,
      initialValue: [],
      update: setCreators
    }
  ];

  const currentFullFields = useMemo(() => {
    return defaultFields
      .map(({ data, field }) => (!isNil(data) && !isEmpty(data) ? field : null))
      .filter((item) => item);
  }, [creationDate, isRevoked, expirationDate, users.length, creators.length]);

  const constructData = ({ value }) => {
    const newData = value
      .split(',')
      .map((simpleValue) => {
        return { id: crypto.randomUUID(), name: simpleValue };
      })
      .filter((item) => item) as Array<PersonalInformation>;

    return [...newData];
  };

  const initializeFullFields = (searchableField) => {
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

  const updateContentFields = (searchableField) => {
    searchableField.forEach(({ field, value }) => {
      if (equals(Fields.CreatorName, field)) {
        setCreators(constructData({ value }));
      }

      if (equals(Fields.UserName, field)) {
        setUsers(constructData({ value }));
      }
      if (equals(Fields.ExpirationDate, field)) {
        const result = constructData({
          value
        });
        const date = dayjs(result[result.length - 1].name).toDate();
        setExpirationDate(date);
      }
      if (equals(Fields.CreationDate, field)) {
        const result = constructData({
          value
        });
        const date = dayjs(result[result.length - 1].name).toDate();
        setCreationDate(date);
      }
      if (equals(Fields.IsRevoked, field)) {
        const result = constructData({
          value
        });

        setIsRevoked(convertToBoolean(result[result.length - 1].name));
      }
    });
  };

  useMemo(() => {
    const searchableFieldInSearchInput = getFoundFields({
      fields: Object.values(Fields),
      value: search
    });

    if (isEmpty(searchableFieldInSearchInput)) {
      initialize();

      return;
    }

    initializeFullFields(searchableFieldInSearchInput);

    updateContentFields(searchableFieldInSearchInput);
  }, [search]);
};

export default useBuildFilterValues;
