import { ChangeEvent } from 'react';

import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';
import { path, split } from 'ramda';

import {
  FormControlLabel,
  Checkbox as MuiCheckbox,
  FormGroup,
  Box
} from '@mui/material';

import { useMemoComponent } from '../..';

import { InputPropsWithoutGroup } from './models';

const useStyles = makeStyles()((theme) => ({
  checkbox: { padding: theme.spacing(0.5) },
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    width: 'fit-content'
  },
  icon: {
    fontSize: theme.spacing(12)
  },
  text: {
    fontSize: theme.spacing(1.5)
  }
}));

const MultiCheckbox = ({
  change,
  checkbox,
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  const { classes } = useStyles();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const value = path(fieldNamePath, values);

  const handleChange =
    (label) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
      const newValue = value?.map((item) => {
        if (item.label === label) {
          return { ...item, value: event.target.checked };
        }

        return item;
      });

      if (change) {
        change({ setFieldValue, value: newValue });

        return;
      }
      setFieldValue(fieldName, newValue);
    };

  return useMemoComponent({
    Component: (
      <FormGroup row={checkbox?.row || false}>
        {value?.map(({ label, value: checked, Icon }) => {
          return (
            <Box className={classes.container} key={label}>
              {Icon && <Icon className={classes.icon} />}
              <FormControlLabel
                control={
                  <MuiCheckbox
                    checked={checked}
                    className={classes.checkbox}
                    color="primary"
                    size="small"
                    onChange={handleChange(label)}
                  />
                }
                key={label}
                label={label}
                labelPlacement={checkbox?.labelPlacement}
              />
            </Box>
          );
        })}
      </FormGroup>
    ),
    memoProps: [value]
  });
};

export default MultiCheckbox;
