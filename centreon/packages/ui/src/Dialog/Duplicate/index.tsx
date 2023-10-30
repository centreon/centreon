import { useState } from 'react';

import { isEmpty, or } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import TextField from '@mui/material/TextField';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & {
  labelInput?: string;
  limit?: number;
};

const useStyles = makeStyles()((theme) => ({
  container: {
    width: theme.spacing(30)
  }
}));

const Duplicate = ({
  labelInput = 'Count',
  labelConfirm = 'Duplicate',
  labelTitle = 'Duplicate',
  limit = Infinity,
  onConfirm,
  ...rest
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const [value, setValue] = useState(1);

  const handleChange = ({ target }): void => {
    setValue(target.value);
  };

  const handleConfirm = (event): void => {
    onConfirm(event, value);
  };

  const isConfirmDisabled = or(isEmpty(value), parseInt(value, 10) > limit);

  return (
    <Dialog
      confirmDisabled={isConfirmDisabled}
      labelConfirm={labelConfirm}
      labelTitle={labelTitle}
      maxWidth="xs"
      onConfirm={handleConfirm}
      {...rest}
    >
      <TextField
        autoFocus
        fullWidth
        className={classes.container}
        color="primary"
        inputProps={{
          'aria-label': 'Duplications',
          max: limit,
          min: 1
        }}
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
