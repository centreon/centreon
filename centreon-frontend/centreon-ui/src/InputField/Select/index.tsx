import React from 'react';

import clsx from 'clsx';

import {
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  makeStyles,
  Theme,
} from '@material-ui/core';

const useStyles = makeStyles((theme: Theme) => ({
  noLabelInput: {
    padding: theme.spacing(1.5),
  },
}));

interface SelectEntry {
  id: number;
  name: string;
}

interface Props {
  options: Array<SelectEntry>;
  onChange;
  selectedOptionId: number;
  label?: string;
}

const SelectField = ({
  options,
  onChange,
  selectedOptionId,
  label,
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <FormControl variant="filled" size="small">
      {label && <InputLabel>{label}</InputLabel>}
      <Select
        inputProps={{
          className: clsx({ [classes.noLabelInput]: !label }),
        }}
        value={selectedOptionId}
        onChange={onChange}
        disableUnderline
      >
        {options.map(({ id, name }) => (
          <MenuItem key={id} value={id}>
            {name}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  );
};

export default SelectField;
