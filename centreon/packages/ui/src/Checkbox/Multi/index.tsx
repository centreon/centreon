import { includes } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { FormGroup } from '@mui/material';

import SingleCheckbox, { LabelPlacement } from '../Single';

interface Props {
  disabled?: boolean;
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
  options: Array<string>;
  row?: boolean;
  values: Array<string>;
}

const useStyles = makeStyles()((theme) => ({
  checkbox: {
    display: 'flex',
    justifyContent: 'center',
    minWidth: theme.spacing(10)
  },
  container: {
    display: 'flex',
    gap: 1
  }
}));

const MultiCheckbox = ({
  options,
  values,
  row = false,
  onChange,
  labelPlacement = 'end',
  disabled = false
}: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <FormGroup className={classes.container} row={row}>
      {options.map((value) => {
        return (
          <SingleCheckbox
            checked={includes(value, values)}
            className={classes.checkbox}
            disabled={disabled}
            key={value}
            label={value}
            labelPlacement={labelPlacement}
            onChange={onChange}
          />
        );
      })}
    </FormGroup>
  );
};

export default MultiCheckbox;
