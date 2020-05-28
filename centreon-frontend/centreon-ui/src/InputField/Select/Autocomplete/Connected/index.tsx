import * as React from 'react';

import { useDebouncedCallback } from 'use-debounce';

import { getData } from '../../../../api';
import { SelectEntry } from '../..';
import { Props as AutocompleteFieldProps } from '..';
import useRequest from '../../../../api/useRequest';

type Props = {
  baseEndpoint: string;
  getSearchEndpoint: (searchField: string) => string;
  getOptionsFromResult: (result) => Array<SelectEntry>;
} & Omit<AutocompleteFieldProps, 'options'>;

export default (
  AutocompleteField: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  const ConnectedAutocompleteField = <TData extends Record<string, unknown>>({
    baseEndpoint,
    getSearchEndpoint,
    getOptionsFromResult,
    ...props
  }: Props): JSX.Element => {
    const [options, setOptions] = React.useState<Array<SelectEntry>>();
    const [optionsOpen, setOptionsOpen] = React.useState<boolean>(false);
    const [searchValue, setSearchValue] = React.useState<string>('');

    const { sendRequest, sending } = useRequest<TData>({
      request: getData,
    });

    const loadOptions = (endpoint): void => {
      sendRequest(endpoint).then((result) =>
        setOptions(getOptionsFromResult(result)),
      );
    };

    const [debouncedChangeText] = useDebouncedCallback((value: string) => {
      loadOptions(getSearchEndpoint(value));
    }, 500);

    const changeText = (event): void => {
      setSearchValue(event.target.value);
      debouncedChangeText(event.target.value);
    };

    const openOptions = (): void => {
      setOptionsOpen(true);
    };

    const closeOptions = (): void => {
      setOptionsOpen(false);
    };

    React.useEffect(() => {
      if (!optionsOpen) {
        setSearchValue('');
        return;
      }

      loadOptions(baseEndpoint);
    }, [optionsOpen]);

    const loading = sending || !options;

    const inputValueProps = optionsOpen ? { inputValue: searchValue } : {};

    return (
      <AutocompleteField
        onOpen={openOptions}
        onClose={closeOptions}
        options={options || []}
        onTextChange={changeText}
        loading={loading}
        {...inputValueProps}
        {...props}
      />
    );
  };

  return ConnectedAutocompleteField;
};
