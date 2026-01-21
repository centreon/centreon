import TextField from '@mui/material/TextField';

import { isEmpty, or } from 'ramda';
import { useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import Dialog, { type Props as DialogProps } from '..';

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
  limit = Number.POSITIVE_INFINITY,
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

  const isConfirmDisabled = or(
    isEmpty(value),
    Number.parseInt(value, 10) > limit
  );

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
        className={classes.container}
        color="primary"
        fullWidth
        label={labelInput}
        margin="dense"
        onChange={handleChange}
        slotProps={{
          htmlInput: {
            'aria-label': 'Duplications',
            max: limit,
            min: 1
          }
        }}
        type="number"
        value={value}
      />
    </Dialog>
  );
};

export default Duplicate;
