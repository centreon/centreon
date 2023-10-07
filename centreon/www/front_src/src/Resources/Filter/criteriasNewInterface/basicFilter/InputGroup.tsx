import { equals, isNil } from 'ramda';

import { MultiConnectedAutocompleteField, SelectEntry } from '@centreon/ui';

import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';
import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { ChangedCriteriaParams, SectionType } from '../model';

import useSectionsData from './sections/useSections';

interface ParametersGetEndpoint {
  page: number;
  search: string;
}

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  label?: string;
  resourceType?: SectionType;
}

const InputGroup = ({
  data,
  filterName,
  changeCriteria,
  label,
  resourceType
}: Props): JSX.Element => {
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });

  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  const { value } = useInputCurrentValues({
    content: dataByFilterName?.value,
    data: dataByFilterName?.value
  });

  const currentLabel = label || dataByFilterName?.label || '';

  const getEndpoint = ({ search, page }: ParametersGetEndpoint): string =>
    dataByFilterName?.buildAutocompleteEndpoint({
      limit: 10,
      page,
      search
    });

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  const getUniqueOptions = (options: Array<SelectEntry>): Array<SelectEntry> =>
    removeDuplicateFromObjectArray({
      array: options,
      byFields: ['name']
    });

  const handleChange = (_, updatedValue): void => {
    changeCriteria({
      filterName,
      updatedValue
    });
  };

  return (
    <MultiConnectedAutocompleteField
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={currentLabel}
      placeholder={currentLabel}
      search={dataByFilterName?.autocompleteSearch}
      value={value}
      onChange={handleChange}
    />
  );
};

export default InputGroup;
