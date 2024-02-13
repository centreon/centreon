import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { equals, isEmpty } from 'ramda';

import { getFoundFields } from '@centreon/ui';

import { searchAtom } from '../Search/atoms';
import { PersonalInformation } from '../../models';

import { usersAtom } from './atoms';
import { Fields } from './models';

const useBuildFilterValues = () => {
  const [search, setSearch] = useAtom(searchAtom);
  const [users, setUsers] = useAtom(usersAtom);
  useMemo(() => {
    const searchableField = getFoundFields({
      fields: Object.values(Fields),
      value: search
    });
    if (isEmpty(searchableField)) {
      return;
    }

    searchableField.forEach(({ field, value }) => {
      if (equals(field, Fields.UserName)) {
        const newUsers = value
          .split(',')
          .map((newUser) => {
            return users.every((user) => user.name !== newUser)
              ? { id: 0, name: newUser }
              : null;
          })
          .filter((item) => item) as Array<PersonalInformation>;

        setUsers([...users, ...newUsers]);
      }
    });
  }, [search]);
};

export default useBuildFilterValues;
