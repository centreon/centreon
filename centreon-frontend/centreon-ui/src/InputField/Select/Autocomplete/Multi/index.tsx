import * as React from 'react';

import { Checkbox, Chip, makeStyles } from '@material-ui/core';

import { UseAutocompleteMultipleProps } from '@material-ui/lab';
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

export type Props = Omit<
  AutocompleteProps,
  'renderTags' | 'renderOption' | 'multiple'
> &
  Omit<UseAutocompleteMultipleProps<SelectEntry>, 'multiple'>;

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
      renderOption={(option, { selected }): JSX.Element => (
        <>
          <Checkbox
            color="primary"
            checked={selected}
            className={classes.checkbox}
          />
          {option.name}
        </>
      )}
      renderTags={renderTags}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
