import React from 'react';

import clsx from 'clsx';
import { isNil } from 'ramda';

import {
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  makeStyles,
  Theme,
  SelectProps,
  FormHelperText,
} from '@material-ui/core';

const useStyles = makeStyles((theme: Theme) => ({
  noLabelInput: {
    padding: theme.spacing(1.5),
  },
  compact: {
    padding: theme.spacing(0.5),
    fontSize: 'x-small',
  },
}));

export interface SelectEntry {
  id: number | string;
  name: string;
  color?: string;
  url?: string;
}

type Props = {
  options: Array<SelectEntry>;
  onChange;
  selectedOptionId: number | string;
  label?: string;
  error?: string;
  compact?: boolean;
  ariaLabel?: string;
} & Omit<SelectProps, 'error'>;

const SelectField = ({
  options,
  onChange,
  selectedOptionId,
  label,
  error,
  fullWidth,
  ariaLabel,
  inputProps,
  compact = false,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <FormControl
      variant="filled"
      size="small"
      error={!isNil(error)}
      fullWidth={fullWidth}
    >
      {label && <InputLabel>{label}</InputLabel>}
      <Select
        inputProps={{
          'aria-label': ariaLabel,
          className: clsx({
            [classes.noLabelInput]: !label && !compact,
            [classes.compact]: compact,
          }),
          ...inputProps,
        }}
        value={selectedOptionId}
        onChange={onChange}
        disableUnderline
        fullWidth={fullWidth}
        {...props}
      >
        {options.map(({ id, name, color }) => (
          <MenuItem
            key={`${id}-${name}`}
            value={id}
            style={{ backgroundColor: color }}
          >
            {name}
          </MenuItem>
        ))}
      </Select>
      {error && <FormHelperText>{error}</FormHelperText>}
    </FormControl>
  );
};

export default SelectField;
