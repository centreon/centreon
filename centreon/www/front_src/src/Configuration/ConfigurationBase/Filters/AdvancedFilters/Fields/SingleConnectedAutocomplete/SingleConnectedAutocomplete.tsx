import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';
import type { JsonDecoder } from 'ts.data.json';

import useSingleConnectedAutocomplete from './useSingleConnectedAutocomplete';

interface Props<TFilters> {
  label: string;
  name: string;
  getEndpoint: () => string;
  baseEndpoint?: string;
  decoder?: JsonDecoder.Decoder<unknown>;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const SingleConnectedAutocomplete = <TFilters,>({
  name,
  label,
  getEndpoint,
  baseEndpoint,
  decoder,
  setFilters,
  filters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const { isOptionEqualToValue, change, value } =
    useSingleConnectedAutocomplete<TFilters>({
      filters,
      name,
      setFilters
    });

  return (
    <SingleConnectedAutocompleteField
      baseEndpoint={baseEndpoint}
      dataTestId={label}
      decoder={
        decoder as React.ComponentProps<
          typeof SingleConnectedAutocompleteField
        >['decoder']
      }
      disableClearable={false}
      field="name"
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(label)}
      onChange={change}
      value={value}
    />
  );
};

export default SingleConnectedAutocomplete;
