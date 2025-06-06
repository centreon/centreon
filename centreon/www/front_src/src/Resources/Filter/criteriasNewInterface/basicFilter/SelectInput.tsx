import { useAtomValue } from 'jotai';
import { equals, find, isEmpty, isNil, propEq, reject, type } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  MultiConnectedAutocompleteField,
  type SelectEntry
} from '@centreon/ui';

import { selectedVisualizationAtom } from '../../../Actions/actionsAtoms';
import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import { Visualization } from '../../../models';
import { labelHost, labelService } from '../../../translatedLabels';
import type { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import {
  type ChangedCriteriaParams,
  type DeactivateProps,
  SectionType
} from '../model';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

import { serviceNamesEndpoint } from '../../api/endpoint';
import { useStyles } from './sections/sections.style';
import useSectionsData from './sections/useSections';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
}

const label = {
  [SectionType.host]: labelHost,
  [SectionType.service]: labelService
};

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria,
  isDeactivated
}: Props & DeactivateProps): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const visualization = useAtomValue(selectedVisualizationAtom);

  const { sectionData } = useSectionsData({ data, sectionType: resourceType });
  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  const resourceTypesCriteria = data.find(({ name }) =>
    equals(name, 'resource_types')
  )?.value as Array<SelectEntry>;

  const value = dataByFilterName?.value?.map((item) => ({
    id: item?.valueId ?? item.id,
    name: item?.name
  })) as Array<SelectEntry>;

  if (!dataByFilterName || isDeactivated) {
    return null;
  }

  const updateResourceType = (updatedValue): void => {
    if (!equals(visualization, Visualization.All)) {
      return;
    }

    if (isNil(resourceTypesCriteria)) {
      return;
    }

    if (isEmpty(updatedValue)) {
      changeCriteria({
        filterName: 'resource_types',
        updatedValue: reject(propEq(resourceType, 'id'), resourceTypesCriteria)
      });

      return;
    }

    if (find(propEq('id', resourceType), resourceTypesCriteria)) {
      return;
    }

    const updatedValues = [
      ...resourceTypesCriteria,
      {
        id: resourceType,
        name: resourceType
      }
    ];

    const uniqUpdatedValues = [
      ...new Map(updatedValues.map((item) => [item.id, item]))
    ].map(([, item]) => item);

    changeCriteria({
      filterName: 'resource_types',
      updatedValue: uniqUpdatedValues
    });
  };

  const handleChange = (_, updatedValue): void => {
    const newValue = updatedValue.map((currentValue) => {
      if (equals(type(currentValue), 'String')) {
        return {
          id: 0,
          name: currentValue
        };
      }

      return currentValue;
    });

    updateResourceType(updatedValue);

    changeCriteria({
      filterName,
      updatedValue: newValue
    });
  };

  const onDelete = (_, option): void => {
    const updatedValue = reject(propEq(option.name, 'name'), value);

    updateResourceType(updatedValue);

    changeCriteria({
      filterName,
      updatedValue
    });
  };

  const getEndpoint = ({ search, page }): string => {
    return buildResourcesEndpoint({
      endpoint: equals(resourceType, SectionType.service)
        ? serviceNamesEndpoint
        : undefined,
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
    const formattedOption = typeof option === 'string' ? option : option.name;
    const formattedSelectedValue =
      typeof selectedValue === 'string' ? selectedValue : selectedValue.name;

    return isNil(formattedOption) || isNil(formattedSelectedValue)
      ? false
      : equals(formattedOption.toString(), formattedSelectedValue.toString());
  };

  return (
    <MultiConnectedAutocompleteField
      disableSortedOptions
      freeSolo
      chipProps={{
        onDelete
      }}
      className={classes.input}
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      textFieldSlotsAndSlotProps={{
        slotProps: { htmlInput: { 'data-testid': resourceType } }
      }}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(label[resourceType]) as string}
      placeholder={t(label[resourceType]) as string}
      search={dataByFilterName?.autocompleteSearch}
      value={value}
      onChange={handleChange}
      exclusionOptionProperty="name"
    />
  );
};

export default SelectInput;
