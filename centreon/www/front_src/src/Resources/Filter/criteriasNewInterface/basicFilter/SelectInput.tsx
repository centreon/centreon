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
import { findData, removeDuplicateFromObjectArray } from '../utils';

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

  const handleSearchData = (updatedValue): SearchData | undefined => {
    const values = dataByFilterName?.search_data?.values;

    const formattedUpdatedValues = updatedValue.map((item) => {
      return { id: resourceType, value: item.name, valueId: item?.id };
    });

    const filteredFormattedUpdatedValues = values?.filter(
      (item) => item?.id !== resourceType
    );

    const result = filteredFormattedUpdatedValues
      ? [...filteredFormattedUpdatedValues, ...formattedUpdatedValues]
      : formattedUpdatedValues;

    return {
      ...dataByFilterName?.search_data,
      values: result as Array<SearchedDataValue>
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

  const handleChange = (_, updatedValue): void => {
    const search_data = handleSearchData(updatedValue);
    const selectedValues = handleValues();

    changeCriteria({
      filterName,
      search_data,
      updatedValue: selectedValues
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

  const onDelete = (_, option): void => {
    const field = dataByFilterName.search_data?.field as string;
    const values = dataByFilterName.search_data?.values;

    const search = searchData?.search;
    const setSearch = searchData?.setSearch;

    const result = getFoundFields({
      fields: [field],
      value: search
    });
    const oldFormattedValue = `${result[0].field}:${result[0].value}`;

    const newValues = result[0]?.value
      ?.split(',')
      ?.filter((item) => item !== option?.name);

    const newFormattedValue = `${field}:${newValues.join(',')}`;

    const newSearch = search?.replace(oldFormattedValue, newFormattedValue);

    setSearch(newSearch);

    const search_data = {
      ...dataByFilterName.search_data,
      values: values?.filter((item) => item.value !== option.name)
    } as SearchData;

    changeCriteria({
      filterName,
      search_data,
      updatedValue: dataByFilterName.value
    });
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
