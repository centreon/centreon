import { useTranslation } from 'react-i18next';

import { SelectEntry, SingleConnectedAutocompleteField } from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import {
  Criteria,
  CriteriaDisplayProps,
  SearchData,
  SearchedDataValue
} from '../../Criterias/models';
import { ChangedCriteriaParams, SectionType } from '../model';
import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { findData, removeDuplicateFromObjectArray } from '../utils';

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
  const { dataByFilterName, valueSearchData } = useInputData({
    data: sectionData,
    filterName,
    resourceType
  });

  const { value, setValue } = useInputCurrentValues({
    content: { id: valueSearchData?.valueId, name: valueSearchData?.value },
    data: valueSearchData
  });

  if (!dataByFilterName) {
    return null;
  }

  const handleSearchData = (updatedValue): SearchData | undefined => {
    const values = dataByFilterName?.searchData?.values;
    const currentValue = {
      id: resourceType,
      value: updatedValue.name,
      valueId: updatedValue.id
    };

    const searchedValues = removeDuplicateFromObjectArray({
      array: values ? [...values, currentValue] : [currentValue],
      byFields: ['id']
    });

    return {
      ...dataByFilterName?.searchData,
      values: searchedValues as Array<SearchedDataValue>
    } as SearchData;
  };

  const handleValues = (): Array<SelectEntry> | [] => {
    const selectedValue = {
      id: resourceType,
      name: findData({
        data: dataByFilterName?.options,
        filterName: resourceType,
        findBy: 'id'
      })?.name
    };

    const dataByFilterNameValue = dataByFilterName?.value;

    return removeDuplicateFromObjectArray({
      array: dataByFilterNameValue
        ? [...dataByFilterNameValue, selectedValue]
        : [selectedValue],
      byFields: ['id']
    }) as Array<SelectEntry>;
  };

  const handleChange = (updatedValue): void => {
    const searchData = handleSearchData(updatedValue);
    const selectedValues = handleValues();

    changeCriteria({
      filterName,
      searchData,
      updatedValue: selectedValues
    });
  };

  const initializeInput = (): void => {
    const initializedSearchedData = dataByFilterName?.searchData?.values.filter(
      (item) => item?.id !== resourceType
    );

    const updatedValue = dataByFilterName?.value?.filter(
      (item) => item?.id !== resourceType
    );

    setValue([]);
    changeCriteria({
      filterName,
      searchData: {
        ...dataByFilterName?.searchData,
        values: initializedSearchedData
      },
      updatedValue
    });
  };

  const onInputChange = (event, valueInput): void => {
    if (!event) {
      return;
    }
    if (valueInput) {
      return;
    }

    initializeInput();
  };

  const getEndpoint = ({ search, page }): string => {
    return buildResourcesEndpoint({
      limit: 10,
      page,
      resourceTypes: [resourceType],
      search
    });
  };

  return (
    <SingleConnectedAutocompleteField
      field="name"
      getEndpoint={getEndpoint}
      label={t(resourceType) as string}
      placeholder={dataByFilterName.label}
      value={value}
      onChange={(_, updatedValue): void => handleChange(updatedValue)}
      onInputChange={onInputChange}
    />
  );
};

export default SelectInput;
