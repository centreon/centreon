import * as React from 'react';

import EditIcon from '@mui/icons-material/Edit';
import { InputAdornment } from '@mui/material';

import AutocompleteField from '.';

export default { title: 'InputField/Autocomplete' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' }
];

const EndAdornment = (): JSX.Element => (
  <InputAdornment position="end">
    <EditIcon />
  </InputAdornment>
);

export const openWithThreeOptions = (): JSX.Element => {
  return (
    <AutocompleteField
      open
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={options[1]}
    />
  );
};

export const closeWithEndAdornment = (): JSX.Element => {
  return (
    <AutocompleteField
      endAdornment={<EndAdornment />}
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={options[1]}
    />
  );
};

export const required = (): JSX.Element => {
  return (
    <AutocompleteField
      required
      endAdornment={<EndAdornment />}
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={options[1]}
    />
  );
};

export const closeWithError = (): JSX.Element => {
  return (
    <AutocompleteField
      freeSolo
      endAdornment={<EndAdornment />}
      error="Error"
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={options[1]}
    />
  );
};

const autoSizeOptions = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity with a veryyyyy long label' },
  { id: 2, name: 'Another third entity option' }
];

interface AutoSizeAutocompleteFieldProps {
  customPadding?: number;
  endAdornment?: JSX.Element;
}

const AutoSizeAutocompleteField = ({
  endAdornment,
  customPadding
}: AutoSizeAutocompleteFieldProps): JSX.Element => {
  const [value, setValue] = React.useState(autoSizeOptions[1]);

  const change = (_, newValue): void => {
    setValue(newValue);
  };

  return (
    <AutocompleteField
      autoSize
      autoSizeCustomPadding={customPadding}
      endAdornment={endAdornment}
      label="Autocomplete"
      options={autoSizeOptions}
      placeholder="Type here..."
      value={value}
      onChange={change}
    />
  );
};

export const autoSize = (): JSX.Element => {
  return <AutoSizeAutocompleteField />;
};

export const autoSizeWithCustomPadding = (): JSX.Element => {
  return (
    <AutoSizeAutocompleteField
      customPadding={5}
      endAdornment={<EndAdornment />}
    />
  );
};
