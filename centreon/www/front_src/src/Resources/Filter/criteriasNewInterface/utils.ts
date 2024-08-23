import { compose, equals, pick, prop, sortBy, toLower, uniqBy } from 'ramda';

import { ResourceType } from '../../models';
import {
  Criteria,
  CriteriaDisplayProps,
  CriteriaNames
} from '../Criterias/models';

import {
  CategoryHostStatus,
  CategoryServiceStatus,
  FieldInformationFromSearchInput,
  FindData,
  HandleDataByCategoryFilter,
  MergeArraysByField,
  ParametersFieldInformation,
  ParametersRemoveDuplicate,
  SectionType
} from './model';

const statusBySectionType = (
  sectionType: SectionType | ResourceType
): Array<string> => {
  return sectionType === ResourceType.service
    ? Object.keys(CategoryServiceStatus)
    : Object.keys(CategoryHostStatus);
};

export const handleDataByCategoryFilter = ({
  data,
  fieldToUpdate,
  filter
}: HandleDataByCategoryFilter): Array<Criteria & CriteriaDisplayProps> => {
  const target = CriteriaNames.statuses;

  const dataToCheck = statusBySectionType(filter as SectionType);

  return data.map((item) => {
    if (item.name !== target) {
      return item;
    }

    const filteredData = item[fieldToUpdate]?.filter(({ id }) =>
      dataToCheck.some(equals(id))
    );

    return { ...item, [fieldToUpdate]: filteredData };
  });
};

export const mergeArraysByField = ({
  firstArray,
  secondArray,
  mergeBy
}: MergeArraysByField): Array<unknown> => {
  return firstArray.map((item) => {
    const objectWithSameKey = secondArray.find(
      (itemSecondArray) => itemSecondArray?.[mergeBy] === item?.[mergeBy]
    );

    return { ...item, ...objectWithSameKey };
  });
};

export const findData = ({
  filterName,
  data,
  findBy = 'name'
}: FindData): Array<Criteria & CriteriaDisplayProps> => {
  const element = data?.find((item) => item[findBy] === filterName);

  return element ? [element] : [];
};

export const findFieldInformationFromSearchInput = ({
  search,
  field
}: ParametersFieldInformation): FieldInformationFromSearchInput => {
  const fieldInformation = search
    .split(' ')
    .find((item) => item.includes(field));
  const fieldEntries = fieldInformation?.split(':');
  const content = fieldEntries?.filter((item) => item !== field).join() ?? '';

  return { content, fieldInformation };
};

export const replaceValueFromSearchInput = ({
  search,
  targetField,
  newContent
}): string => {
  const array = search.split(' ');

  const targetByIndex = array.findIndex((item) => item === targetField);

  const result = array.map((item, index) => {
    return index === targetByIndex ? newContent : item;
  });

  return result.join(' ');
};

export const removeDuplicateFromObjectArray = ({
  array,
  byFields
}: ParametersRemoveDuplicate): Array<unknown> => uniqBy(pick(byFields), array);

export const sortByNameCaseInsensitive = sortBy(compose(toLower, prop('name')));

export const escapeRegExpSpecialChars = (input: string): string => {
  return input.replace(/[.*+?^${}()|[\]\\|\\/]/g, '\\$&');
};
