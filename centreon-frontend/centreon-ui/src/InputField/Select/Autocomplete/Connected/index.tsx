import * as React from 'react';

import { useDebouncedCallback } from 'use-debounce';

import AutocompleteField, { Props as AutocompleteFieldProps } from '..';
import useCancelTokenSource from '../../../../api/useCancelTokenSource';
import { getData } from '../../../../api';
import { SelectEntry } from '../..';

interface Props {
  baseEndpoint: string;
  getSearchEndpoint: (searchField: string) => string;
  getOptionsFromResult: (result) => Array<SelectEntry>;
}

const ConnectedAutocompleteField = <TData extends Record<string, unknown>>({
  baseEndpoint,
  getSearchEndpoint,
  getOptionsFromResult,
  ...props
}: Props & Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
  const [options, setOptions] = React.useState<Array<SelectEntry>>();
  const [open, setOpen] = React.useState(false);
  const [loading, setLoading] = React.useState(true);
  const [searchValue, setSearchValue] = React.useState('');

  const { token, cancel } = useCancelTokenSource();

  const loadOptions = (endpoint): void => {
    setLoading(true);
    getData<TData>({
      endpoint,
      requestParams: { token },
    })
      .then((result) => {
        setOptions(getOptionsFromResult(result));
      })
      .catch(() => setOptions([]))
      .finally(() => setLoading(false));
  };

  const [debouncedChangeText] = useDebouncedCallback((value: string) => {
    loadOptions(getSearchEndpoint(value));
  }, 500);

  const changeText = (event): void => {
    setSearchValue(event.target.value);
    debouncedChangeText(event.target.value);
  };

  const doOpen = (): void => {
    setOpen(true);
  };

  const close = (): void => {
    setOpen(false);
  };

  React.useEffect(() => {
    return (): void => cancel();
  }, []);

  React.useEffect(() => {
    if (!open) {
      setSearchValue('');
      return;
    }

    loadOptions(baseEndpoint);
  }, [open]);

  return (
    <AutocompleteField
      onOpen={doOpen}
      onClose={close}
      options={options || []}
      onTextChange={changeText}
      loading={loading}
      inputValue={searchValue}
      {...props}
    />
  );
};

export default ConnectedAutocompleteField;
