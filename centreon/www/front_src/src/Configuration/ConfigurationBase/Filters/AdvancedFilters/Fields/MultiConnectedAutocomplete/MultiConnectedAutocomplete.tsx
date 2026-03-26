import { MultiConnectedAutocompleteField } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import useMultiConnectedAutocomplete from './useMultiConnectedAutocomplete';

interface Props<TFilters> {
  label: string;
  name: string;
  getEndpoint: () => string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const MultiConnectedAutocomplete = <TFilters,>({
  name,
  label,
  getEndpoint,
  setFilters,
  filters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const { isOptionEqualToValue, deleteItem, change, value } =
    useMultiConnectedAutocomplete<TFilters>({
      filters,
      name,
      setFilters
    });

  return (
    <MultiConnectedAutocompleteField
      chipProps={{
        color: 'primary',
        onDelete: deleteItem(name)
      }}
      dataTestId={label}
      disableClearable={false}
      disableSortedOptions
      field="name"
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(label)}
      onChange={change}
      value={value}
    />
  );
};

export default MultiConnectedAutocomplete;
