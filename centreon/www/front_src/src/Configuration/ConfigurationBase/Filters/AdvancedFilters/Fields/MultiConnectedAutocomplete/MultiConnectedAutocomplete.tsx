import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField } from '@centreon/ui';
import { SetStateAction } from 'jotai';
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
      name,
      setFilters,
      filters
    });

  return (
    <MultiConnectedAutocompleteField
      disableClearable={false}
      disableSortedOptions
      chipProps={{
        color: 'primary',
        onDelete: deleteItem(name)
      }}
      dataTestId={label}
      field="name"
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(label)}
      value={value}
      onChange={change}
    />
  );
};

export default MultiConnectedAutocomplete;
