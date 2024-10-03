import { equals, isNil, propEq, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { ChangedCriteriaParams, SectionType } from '../model';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

import { useStyles } from './sections/sections.style';
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
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });

  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  const value = dataByFilterName?.value as Array<SelectEntry>;

  if (!dataByFilterName) {
    return null;
  }

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

  const onDelete = (_, option): void => {
    const updatedValue = reject(propEq('name', option.name), value);

    changeCriteria({
      filterName,
      updatedValue
    });
  };

  return (
    <MultiConnectedAutocompleteField
      chipProps={{
        onDelete
      }}
      className={classes.input}
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(currentLabel) as string}
      placeholder={t(currentLabel)}
      search={dataByFilterName?.autocompleteSearch}
      value={value}
      onChange={handleChange}
    />
  );
};

export default InputGroup;
