import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  MultiConnectedAutocompleteField,
  SelectEntry,
  getFoundFields
} from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import {
  Criteria,
  CriteriaDisplayProps,
  SearchData,
  SearchDataPropsCriterias,
  SearchedDataValue
} from '../../Criterias/models';
import { ChangedCriteriaParams, SectionType } from '../model';
import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

import useSectionsData from './sections/useSections';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
  searchData?: SearchDataPropsCriterias;
}

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria,
  searchData
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });
  const { dataByFilterName, valueSearchData } = useInputData({
    data: sectionData,
    filterName,
    resourceType
  });

  const content = valueSearchData?.map((item) => ({
    id: item?.valueId,
    name: item?.value
  }));

  const { value } = useInputCurrentValues({
    content,
    data: valueSearchData
  });

  if (!dataByFilterName) {
    return null;
  }

  const search = searchData?.search;
  const setSearch = searchData?.setSearch;
  const searchDataItem = dataByFilterName?.search_data;
  const searchDataValues = dataByFilterName?.search_data?.values;

  const handleSearchData = (updatedValue): SearchData | undefined => {
    const formattedUpdatedValues = updatedValue.map((item) => {
      return {
        id: resourceType,
        value: item.name,
        valueId: item?.id
      };
    });

    const filteredFormattedUpdatedValues = searchDataValues?.filter(
      (item) => item?.id !== resourceType
    );

    const result = filteredFormattedUpdatedValues
      ? [...filteredFormattedUpdatedValues, ...formattedUpdatedValues]
      : formattedUpdatedValues;

    return {
      ...searchDataItem,
      values: result as Array<SearchedDataValue>
    } as SearchData;
  };

  const handleValues = (data): Array<SelectEntry> | [] => {
    const selectedValues = new Set(data?.values?.map((element) => element?.id));
    const updatedValues = Array.from(selectedValues).filter((item) => item);

    return updatedValues.map((item) => ({
      id: item,
      name: dataByFilterName.options?.find((element) => element.id === item)
        ?.name
    }));
  };

  const handleChange = (event, updatedValue): void => {
    const selectedValue = event?.target?.innerText;

    const searchedSelectedValue = updatedValue.find(
      (item) => item.name === selectedValue
    );

    if (!searchedSelectedValue) {
      onDelete(null, { name: selectedValue });

      return;
    }

    const search_data = handleSearchData(updatedValue);
    const selectedValues = handleValues(search_data);

    changeCriteria({
      filterName,
      search_data,
      updatedValue: selectedValues
    });
  };

  const updateSearchInput = (option: SelectEntry): void => {
    const field = searchDataItem?.field as string;

    const result = getFoundFields({
      fields: [field],
      value: search as string
    });
    const oldFormattedValue = `${result[0]?.field}:${result[0]?.value}`;

    const newValues = result[0]?.value
      ?.split(',')
      ?.filter((item) => item !== option?.name);

    const newFormattedValue =
      newValues?.length > 0 ? `${field}:${newValues.join(',')}` : '';

    const newSearch = search?.replace(oldFormattedValue, newFormattedValue);

    setSearch(newSearch);
  };

  const onDelete = (_, option): void => {
    updateSearchInput(option);

    const search_data = {
      ...searchDataItem,
      values: searchDataValues?.filter((item) => item.value !== option.name)
    } as SearchData;

    const updatedValue = handleValues(search_data);

    changeCriteria({
      filterName,
      search_data,
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
      byFields: ['id']
    }) as Array<SelectEntry>;
  };

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  return (
    <MultiConnectedAutocompleteField
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
