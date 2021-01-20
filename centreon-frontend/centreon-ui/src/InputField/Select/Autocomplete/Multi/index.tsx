import * as React from 'react';

import { Checkbox, Chip, makeStyles } from '@material-ui/core';
import { UseAutocompleteProps } from '@material-ui/lab';

import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import Option from '../../Option';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    padding: 0,
    marginRight: theme.spacing(1),
  },
  tag: {
    fontSize: theme.typography.pxToRem(10),
  },
}));

interface MultiAutocompleteProps {
  displayCheckboxOption?: boolean;
}

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
  > &
  MultiAutocompleteProps;

const MultiAutocompleteField = ({
  displayCheckboxOption = true,
  ...props
}: Props): JSX.Element => {
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
          {displayCheckboxOption && (
            <Checkbox
              color="primary"
              size="small"
              checked={selected}
              className={classes.checkbox}
            />
          )}
          <Option>{option.name}</Option>
        </>
      )}
      renderTags={renderTags}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
