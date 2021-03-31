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
      open
      icon={<EditIcon />}
      label="Autocomplete"
      options={options}
      title="Edit"
      value={[options[1]]}
    />
  );
};
