import React from 'react';

import EditIcon from '@material-ui/icons/Edit';

import MultiAutocompleteField from '.';

export default { title: 'InputField/Autocomplete/Multi' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

export const openWithThreeOptions = (): JSX.Element => {
  return (
    <MultiAutocompleteField
      options={options}
      label="Autocomplete"
      placeholder="Type here..."
      value={[options[1]]}
      open
    />
  );
};

export const closeWithEndAdornment = (): JSX.Element => {
  return (
    <MultiAutocompleteField
      options={options}
      label="Autocomplete"
      placeholder="Type here..."
      value={[options[1]]}
      endAdornment={<EditIcon />}
    />
  );
};
