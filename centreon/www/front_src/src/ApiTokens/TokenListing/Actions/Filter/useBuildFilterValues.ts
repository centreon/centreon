import { useEffect, useMemo, useRef } from 'react';

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

const useBuildFilterValues = () => {
  const [search, setSearch] = useAtom(searchAtom);
  const [users, setUsers] = useAtom(usersAtom);
  const [creators, setCreators] = useAtom(creatorsAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);

  const constructData = ({ dataToUpdate, value }) => {
    const newData = value
      .split(',')
      .map((simpleValue) => {
        return dataToUpdate.every((item) => item.name !== simpleValue)
          ? { id: crypto.randomUUID(), name: simpleValue }
          : null;
      })
      .filter((item) => {
        return !isNil(item);
      }) as Array<PersonalInformation>;

    return [...dataToUpdate, ...newData];
  };

  useMemo(() => {
    const searchableField = getFoundFields({
      fields: Object.values(Fields),
      value: search
    });

    if (isEmpty(searchableField)) {
      return;
    }

    searchableField.forEach(({ field, value }) => {
      if (equals(Fields.CreatorName, field)) {
        setCreators(constructData({ dataToUpdate: creators, value }));
      }

      if (equals(Fields.UserName, field)) {
        setUsers(constructData({ dataToUpdate: users, value }));
      }
      if (equals(Fields.ExpirationDate, field)) {
        const result = constructData({
          dataToUpdate: adjustData(expirationDate),
          value
        });
        setExpirationDate(result[result.length - 1].name);
      }
      if (equals(Fields.CreationDate, field)) {
        const result = constructData({
          dataToUpdate: adjustData(creationDate),
          value
        });
        setCreationDate(result[result.length - 1].name);
      }
      if (equals(Fields.IsRevoked, field)) {
        const result = constructData({
          dataToUpdate: adjustData(isRevoked),
          value
        });

        setIsRevoked(convertToBoolean(result[result.length - 1].name));
      }
    });
  }, [search]);
};

export default useBuildFilterValues;
