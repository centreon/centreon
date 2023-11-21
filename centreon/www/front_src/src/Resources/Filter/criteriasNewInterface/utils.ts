import { sortBy, prop, compose, toLower, uniqBy, pick } from 'ramda';

import { ResourceType } from '../../models';
import {
  Criteria,
  CriteriaDisplayProps,
  CriteriaNames
} from '../Criterias/models';

import {
  BasicCriteriaResourceType,
  CallbackCheck,
  CategoryFilter,
  ExtendedCriteriaResourceType,
  FieldInformationFromSearchInput,
  FindData,
  HandleDataByCategoryFilter,
  MergeArraysByField,
  ParametersFieldInformation,
  ParametersRemoveDuplicate,
  SectionType,
  categoryHostStatus,
  categoryServiceStatus
} from './model';

const statusBySectionType = (
  sectionType: SectionType | ResourceType
): Array<string> => {
  return sectionType === ResourceType.service
    ? Object.keys(categoryServiceStatus)
    : Object.keys(categoryHostStatus);
};

const resourceTypesBySection = (categoryFilter): Array<string> => {
  return categoryFilter === CategoryFilter.BasicFilter
    ? Object.values(BasicCriteriaResourceType)
    : Object.values(ExtendedCriteriaResourceType);
};

const callBackCheck = ({ id, dataToCheck }: CallbackCheck): boolean =>
  dataToCheck.some((status) => status === id);

export const handleDataByCategoryFilter = ({
  data,
  fieldToUpdate,
  filter
}: HandleDataByCategoryFilter): Array<Criteria & CriteriaDisplayProps> => {
  const target =
    filter in CategoryFilter
      ? CriteriaNames.resourceTypes
      : CriteriaNames.statuses;

  const dataToCheck =
    filter in CategoryFilter
      ? resourceTypesBySection(filter)
      : statusBySectionType(filter as SectionType);

  return data.map((item) => {
    if (item.name !== target) {
      return item;
    }

    const filteredData = item[fieldToUpdate]?.filter((currentItem) =>
      callBackCheck({ dataToCheck, id: currentItem.id })
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
}: FindData): (Criteria & CriteriaDisplayProps) | undefined =>
  data?.find((item) => item[findBy] === filterName);

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
