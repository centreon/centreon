import React, { useState, useEffect } from 'react';

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
  const [options, setOptions] = useState<Array<SelectEntry>>();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(true);

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

  const changeText = (event): void => {
    loadOptions(getSearchEndpoint(event.target.value));
  };

  const doOpen = (): void => {
    setOpen(true);
  };

  const close = (): void => {
    setOpen(false);
  };

  useEffect(() => {
    return (): void => cancel();
  }, []);

  useEffect(() => {
    if (!open) {
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
      {...props}
    />
  );
};

export default ConnectedAutocompleteField;
