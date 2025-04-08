import EditIcon from '@mui/icons-material/Edit';

import MultiPopoverAutocomplete from './Popover';

import MultiAutocompleteField from '.';

export default { title: 'InputField/Autocomplete/Multi' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' }
];

export const openWithThreeOptions = (): JSX.Element => {
  return (
    <MultiAutocompleteField
      open
      label="Autocomplete"
      options={options}
      placeholder=""
      value={[options[1]]}
    />
  );
};

export const closeWithEndAdornment = (): JSX.Element => {
  return (
    <MultiAutocompleteField
      endAdornment={<EditIcon />}
      label="Autocomplete"
      options={options}
      placeholder="Type here..."
      value={[options[1]]}
    />
  );
};

export const popover = (): JSX.Element => {
  return (
    <MultiPopoverAutocomplete
      label="Popover Autocomplete"
      options={options}
      placeholder="Type here..."
      value={[options[1]]}
    />
  );
};

export const popoverWithoutInput = (): JSX.Element => {
  return (
    <MultiPopoverAutocomplete
      hideInput
      label="Popover Autocomplete"
      options={options}
      placeholder="Type here..."
      value={[options[1]]}
    />
  );
};

export const customRenderedTags = (): JSX.Element => {
  const customRender = (tags: React.ReactNode): React.ReactNode => (
    <div style={{ display: 'flex' }}>
      {tags}
      <span style={{ color: '#999' }}>Custom wrapper</span>
    </div>
  );

  return (
    <MultiAutocompleteField
      label="Custom Tags Render"
      options={options}
      placeholder="Type here..."
      value={[options[0], options[1]]}
      customRenderTags={customRender}
    />
  );
};
