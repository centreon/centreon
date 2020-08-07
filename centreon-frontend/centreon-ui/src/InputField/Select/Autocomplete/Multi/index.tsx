import * as React from 'react';

import { Checkbox, Chip, makeStyles, Typography } from '@material-ui/core';

import { UseAutocompleteProps } from '@material-ui/lab';
import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    padding: 0,
    marginRight: theme.spacing(1),
  },
  tag: {
    fontSize: theme.typography.pxToRem(10),
  },
}));

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export type Props = Omit<
  AutocompleteProps,
  'renderTags' | 'renderOption' | 'multiple'
> &
  Omit<
    UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
    'multiple'
  >;

const MultiAutocompleteField = (props: Props): JSX.Element => {
  const classes = useStyles();

  const renderTags = (value, getTagProps): Array<JSX.Element> =>
    value.map((option, index) => (
      <Chip
        classes={{
          root: classes.tag,
        }}
        key={option.id}
        label={option.name}
        size="small"
        {...getTagProps({ index })}
      />
    ));

  return (
    <Autocomplete
      multiple
      disableCloseOnSelect
      renderOption={(option, { selected }): JSX.Element => (
        <>
          <Checkbox
            color="primary"
            size="small"
            checked={selected}
            className={classes.checkbox}
          />
          <Typography>{option.name}</Typography>
        </>
      )}
      renderTags={renderTags}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
