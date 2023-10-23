import { equals, find, isEmpty, isNil, propEq, reject, type } from 'ramda';
import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField, SelectEntry } from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { ChangedCriteriaParams, SectionType } from '../model';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

import useSectionsData from './sections/useSections';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
}

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });
  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  const resourceTypesCriteria = data.find(({ name }) =>
    equals(name, 'resource_types')
  )?.value as Array<SelectEntry>;

  const value = dataByFilterName?.value?.map((item) => ({
    id: item?.valueId,
    name: item?.name
  })) as Array<SelectEntry>;

  if (!dataByFilterName) {
    return null;
  }

  const updateResourceType = (updatedValue): void => {
    if (isEmpty(updatedValue)) {
      changeCriteria({
        filterName: 'resource_types',
        updatedValue: reject(propEq('id', resourceType), resourceTypesCriteria)
      });
    }

    if (find(propEq('id', resourceType), resourceTypesCriteria)) {
      return;
    }

    changeCriteria({
      filterName: 'resource_types',
      updatedValue: [
        ...resourceTypesCriteria,
        {
          id: resourceType,
          name: resourceType
        }
      ]
    });
  };

  const handleChange = (event, updatedValue): void => {
    const newValue = updatedValue.map((value) => {
      if (equals(type(value), 'String')) {
        return {
          id: 0,
          name: value
        };
      }

      return value;
    });

    updateResourceType(updatedValue);

    changeCriteria({
      filterName,
      updatedValue: newValue
    });
  };

  const onDelete = (_, option): void => {
    const updatedValue = reject(propEq('name', option.name), value);

    updateResourceType(updatedValue);

    changeCriteria({
      filterName,
      updatedValue
    });
  };

  const getEndpoint = ({ search, page }): string => {
    return buildResourcesEndpoint({
      limit: 10,
      page,
      resourceTypes: [resourceType],
      search
    });
  };

  const getUniqueOptions = (
    options: Array<SelectEntry>
  ): Array<SelectEntry> => {
    return removeDuplicateFromObjectArray({
      array: options,
      byFields: ['name']
    }) as Array<SelectEntry>;
  };

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  return (
    <MultiConnectedAutocompleteField
      freeSolo
      chipProps={{
        onDelete
      }}
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(resourceType) as string}
      placeholder={dataByFilterName.label}
      search={dataByFilterName?.autocompleteSearch}
      value={value}
      onChange={handleChange}
    />
  );
};

export default SelectInput;
