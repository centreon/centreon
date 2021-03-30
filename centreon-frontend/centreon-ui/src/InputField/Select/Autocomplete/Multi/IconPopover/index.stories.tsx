import React from 'react';

import EditIcon from '@material-ui/icons/Edit';

import IconPopoverMultiAutocompleteField from '.';

export default { title: 'InputField/Autocomplete/Multi/IconPopover' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

export const withThreeOptions = (): JSX.Element => {
  return (
    <IconPopoverMultiAutocompleteField
      icon={<EditIcon />}
      title="Edit"
      options={options}
      label="Autocomplete"
      value={[options[1]]}
      open
    />
  );
};
