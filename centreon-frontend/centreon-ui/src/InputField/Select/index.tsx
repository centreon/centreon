import * as React from 'react';

import clsx from 'clsx';
import { isNil, propEq } from 'ramda';

import {
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  makeStyles,
  Theme,
  SelectProps,
  FormHelperText,
  ListSubheader,
  Divider,
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
  type?: 'header';
  createOption?: string;
  inputValue?: string;
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

  const getOption = (id): SelectEntry => {
    return options.find(propEq('id', id)) as SelectEntry;
  };

  const changeOption = (event) => {
    if (!isNil(event.target.value)) {
      onChange(event);
    }
  };

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
        onChange={changeOption}
        disableUnderline
        fullWidth={fullWidth}
        displayEmpty
        renderValue={(id) => {
          return getOption(id)?.name;
        }}
        {...props}
      >
        {options
          .filter(({ id }) => id !== '')
          .map(({ id, name, color, type }) => {
            const key = `${id}-${name}`;
            if (type === 'header') {
              return [
                <ListSubheader key={key}>{name}</ListSubheader>,
                <Divider key={`${key}-divider`} />,
              ];
            }

            return (
              <MenuItem key={key} value={id} style={{ backgroundColor: color }}>
                {name}
              </MenuItem>
            );
          })}
      </Select>
      {error && <FormHelperText>{error}</FormHelperText>}
    </FormControl>
  );
};

export default SelectField;
