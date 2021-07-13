import * as React from 'react';

import { isEmpty } from 'ramda';

import { UseAutocompleteProps } from '@material-ui/lab';
import { Avatar, Chip, makeStyles, useTheme } from '@material-ui/core';

import { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import PopoverMenu from '../../../../PopoverMenu';

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export type Props = Omit<AutocompleteProps, 'renderTags' | 'multiple'> &
  Omit<
    UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
    'multiple'
  >;

const useStyles = makeStyles(() => ({
  chip: {
    cursor: 'pointer',
    display: 'flex',
    justifyContent: 'space-between',
    width: '100%',
  },
}));

const PopoverAutocomplete = (
  AutocompleteField: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  const InnerAutocomplete = ({
    value,
    label,
    onChange,
    ...props
  }: Props): JSX.Element => {
    const [optionsOpen, setOptionsOpen] = React.useState<boolean>(false);
    const theme = useTheme();
    const classes = useStyles();

    const icon = (
      <Chip
        avatar={<Avatar>{(value as Array<SelectEntry>).length}</Avatar>}
        className={classes.chip}
        color={isEmpty(value) ? undefined : 'primary'}
        label={label}
        size="small"
        onDelete={(e) => onChange?.(e, [], 'clear')}
      />
    );

    const openOptions = (): void => {
      setOptionsOpen(true);
    };

    const closeOptions = (): void => {
      setOptionsOpen(false);
    };

    return (
      <PopoverMenu icon={icon} onClose={closeOptions} onOpen={openOptions}>
        {() => (
          <AutocompleteField
            autoFocus
            disableCloseOnSelect
            multiple
            displayPopupIcon={false}
            open={optionsOpen}
            renderTags={() => null}
            style={{ minWidth: theme.spacing(20) }}
            value={value}
            onChange={onChange}
            {...props}
          />
        )}
      </PopoverMenu>
    );
  };

  return InnerAutocomplete;
};

export default PopoverAutocomplete;
