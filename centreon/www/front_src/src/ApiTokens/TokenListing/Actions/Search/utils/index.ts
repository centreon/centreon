import { isEmpty } from 'ramda';

import { SelectEntry, getFoundFields } from '@centreon/ui';

import { PersonalInformation } from '../../../models';
import { ClearFields } from '../models';

export const clearFields = ({ input, search }: ClearFields): string | null => {
  const fieldValueToDelete = input
    .map(({ data, field }) => {
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
    })
    .filter((item) => item);

  const updatedSearch = search
    .split(' ')
    .map((word) => {
      return fieldValueToDelete.some((wordToDelete) => wordToDelete === word)
        ? ''
        : word;
    })
    .filter((item) => item)
    .join(' ');

  return !isEmpty(fieldValueToDelete) ? updatedSearch : null;
};

export const getUniqData = (data): Array<PersonalInformation> => {
  const result = [
    ...new Map(data.map((item) => [item.name, item])).values()
  ] as Array<PersonalInformation>;

  return result || [];
};

export const adjustData = (value): Array<SelectEntry> => {
  return [{ id: 0, name: value }];
};

export const convertToBoolean = (input: string): boolean => {
  return input === 'true';
};
