import { useState } from 'react';

import TextField from '@mui/material/TextField';

import Dialog, { Props as DialogProps } from '..';

type Props = {
  labelInput?: string;
} & DialogProps;

const Duplicate = ({
  labelInput = 'Count',
  labelConfirm = 'Duplicate',
  labelTitle = 'Duplicate',
  onConfirm,
  ...rest
}: Props): JSX.Element => {
  const [value, setValue] = useState(1);

  const handleChange = ({ target }): void => {
    setValue(target.value);
  };

  const handleConfirm = (event): void => {
    onConfirm(event);
  };

  return (
    <Dialog
      labelConfirm={labelConfirm}
      labelTitle={labelTitle}
      maxWidth="xs"
      onConfirm={handleConfirm}
      {...rest}
    >
      <TextField
        autoFocus
        fullWidth
        color="primary"
        inputProps={{ 'aria-label': 'Duplications', min: 1 }}
        label={labelInput}
        margin="dense"
        type="number"
        value={value}
        onChange={handleChange}
      />
    </Dialog>
  );
};

export default Duplicate;
