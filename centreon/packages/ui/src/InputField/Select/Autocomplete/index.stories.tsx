import EditIcon from '@mui/icons-material/Edit';

import AutocompleteField from '.';

export default { title: 'InputField/Autocomplete' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' }
];

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
      endAdornment={<EditIcon />}
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
      endAdornment={<EditIcon />}
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
      endAdornment={<EditIcon />}
      error="Error"
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={options[1]}
    />
  );
};
