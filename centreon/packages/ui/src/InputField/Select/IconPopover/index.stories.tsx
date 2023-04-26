import EditIcon from '@mui/icons-material/Edit';

import IconPopoverMultiAutocompleteField from '.';

export default { title: 'InputField/Select/IconPopover' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' }
];

export const withThreeOptions = (): JSX.Element => {
  return (
    <IconPopoverMultiAutocompleteField
      icon={<EditIcon />}
      options={options}
      title="Edit"
      value={[options[1]]}
      onChange={(): undefined => undefined}
    />
  );
};
