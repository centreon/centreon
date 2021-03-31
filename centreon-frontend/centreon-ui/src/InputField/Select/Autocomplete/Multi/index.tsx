import * as React from 'react';

import { Checkbox, Chip, makeStyles } from '@material-ui/core';
import { UseAutocompleteProps } from '@material-ui/lab';

import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import Option from '../../Option';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    marginRight: theme.spacing(1),
    padding: 0,
  },
  deleteIcon: {
    height: theme.spacing(1.5),
    width: theme.spacing(1.5),
  },
  tag: {
    fontSize: theme.typography.caption.fontSize,
    height: theme.spacing(1.75),
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
          deleteIcon: classes.deleteIcon,
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
      disableCloseOnSelect
      multiple
      getLimitTagsText={(more) => <Option>{`+${more}`}</Option>}
      renderOption={(option, { selected }): JSX.Element => (
        <>
          {displayCheckboxOption && (
            <Checkbox
              checked={selected}
              className={classes.checkbox}
              color="primary"
              size="small"
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
