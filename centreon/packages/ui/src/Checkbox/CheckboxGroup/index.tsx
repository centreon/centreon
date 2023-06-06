import { equals, includes } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { FormGroup } from '@mui/material';

import Checkbox, { LabelPlacement } from '../Checkbox';

interface Props {
  dataTestId?: string;
  direction?: 'horizontal' | 'vertical';
  disabled?: boolean;
  labelPlacement?: LabelPlacement;
  onChange?: (e) => void;
  options: Array<string>;
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

const CheckboxGroup = ({
  options,
  values,
  direction = 'vertical',
  onChange,
  labelPlacement = 'end',
  disabled = false,
  dataTestId
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const row = !equals(direction, 'vertical');

  return (
    <FormGroup
      className={classes.container}
      data-testid={dataTestId || ''}
      row={row}
    >
      {options.map((value) => {
        return (
          <Checkbox
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

export default CheckboxGroup;
