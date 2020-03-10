import React from 'react';

import AutocompleteField from '.';

export default { title: 'InputField/Autocomplete' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

export const openWithThreeOptions = (): JSX.Element => {
  return (
    <AutocompleteField
      options={options}
      label="Autocomplete"
      placeholder="Type here..."
      value={[options[1]]}
      open
    />
  );
};
