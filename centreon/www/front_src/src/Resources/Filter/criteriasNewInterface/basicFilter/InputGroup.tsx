import { equals, isNil } from 'ramda';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

import useSectionsData from './sections/useSections';

const InputGroup = ({
  data,
  filterName,
  changeCriteria,
  label,
  resourceType
}) => {
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });

  const { target } = useInputData({
    data: sectionData,
    filterName
  });

  const { value } = useInputCurrentValues({
    content: target?.value,
    data: target?.value
  });

  const displayedColumn = label || target?.label || '';

  const getEndpoint = ({ search, page }): string =>
    target?.buildAutocompleteEndpoint({
      limit: 10,
      page,
      search
    });

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  const getUniqueOptions = (options) =>
    removeDuplicateFromObjectArray({
      array: options,
      byFields: ['name']
    });

  return (
    <MultiConnectedAutocompleteField
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={currentLabel}
      labelKey={displayedColumn}
      placeholder={currentLabel}
      search={target?.autocompleteSearch}
      value={value}
      onChange={(_, updatedValue): void => {
        changeCriteria({
          filterName,
          updatedValue
        });
      }}
    />
  );
};

export default InputGroup;
