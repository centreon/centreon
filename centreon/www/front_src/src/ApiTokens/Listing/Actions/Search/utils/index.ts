import { SelectEntry } from '@centreon/ui';

import { NamedEntity } from '../../../models';

export const getUniqData = (data): Array<NamedEntity> => {
  const result = [
    ...new Map(data.map((item) => [item.name, item])).values()
  ] as Array<NamedEntity>;

  return result || [];
};

export const adjustData = (value): Array<SelectEntry> => {
  return [{ id: 0, name: value }];
};

export const convertToBoolean = (input: string): boolean => {
  return input === 'true';
};

export const translateWhiteSpaceToRegex = (input: string): string => {
  return input.replace(/\s/g, '\\s+');
};
