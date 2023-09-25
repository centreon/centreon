import { ResourceType } from '../../models';
import { CriteriaNames } from '../Criterias/models';

import {
  BasicCriteriaResourceType,
  CategoryFilter,
  ExtendedCriteriaResourceType,
  MergeArraysByField,
  SectionType,
  categoryHostStatus,
  categoryServiceStatus
} from './model';

const statusBySectionType = (sectionType) => {
  return sectionType === ResourceType.service
    ? Object.keys(categoryServiceStatus)
    : Object.keys(categoryHostStatus);
};

const resourceTypesBySection = (categoryFilter) => {
  return categoryFilter === CategoryFilter.BasicFilter
    ? Object.values(BasicCriteriaResourceType)
    : Object.values(ExtendedCriteriaResourceType);
};

const callBackCheck = ({ id, dataToCheck }) =>
  dataToCheck.some((status) => status === id);

interface HandleData {
  data: Array<unknown>;
  fieldToUpdate: string;
  filter: CategoryFilter | SectionType;
}
export const handleDataByCategoryFilter = ({
  data,
  fieldToUpdate,
  filter
}: HandleData): Array<unknown> => {
  const target =
    filter in CategoryFilter
      ? CriteriaNames.resourceTypes
      : CriteriaNames.statuses;

  const dataToCheck =
    filter in CategoryFilter
      ? resourceTypesBySection(filter)
      : statusBySectionType(filter);

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

export const findData = ({ target, data }): any =>
  data?.find((item) => item?.name === target);

export const findFieldInformationFromSearchInput = ({ search, field }) => {
  const target = search.split(' ').find((item) => item.includes(field));
  const fieldEntries = target?.split(':');
  const content = fieldEntries?.filter((item) => item !== field).join() ?? '';

  return { content, target };
};

export const replaceValueFromSearchInput = ({
  search,
  targetField,
  newContent
}) => {
  const array = search.split(' ');

  const targetByIndex = array.findIndex((item) => item === targetField);

  const result = array.map((item, index) =>
    index === targetByIndex ? newContent : item
  );

  return result.join(' ');
};
