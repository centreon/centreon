import { useState } from 'react';

import { isEmpty } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { UseAutocompleteProps } from '@mui/material/useAutocomplete';
import { Avatar, Chip, useTheme } from '@mui/material';

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

const useStyles = makeStyles()((theme) => ({
  chip: {
    borderRadius: theme.spacing(1.5),
    cursor: 'pointer',
    display: 'flex',
    height: theme.spacing(3),
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
    hideInput,
    ...props
  }: Props): JSX.Element => {
    const [optionsOpen, setOptionsOpen] = useState<boolean>(false);
    const theme = useTheme();
    const { classes } = useStyles();

    const icon = (
      <Chip
        avatar={<Avatar>{(value as Array<SelectEntry>).length}</Avatar>}
        className={classes.chip}
        color={isEmpty(value) ? undefined : 'primary'}
        label={label}
        size="small"
        onDelete={(e): void => onChange?.(e, [], 'clear')}
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
        {(): JSX.Element => (
          <AutocompleteField
            autoFocus
            disableCloseOnSelect
            multiple
            displayPopupIcon={false}
            hideInput={hideInput}
            open={optionsOpen}
            renderTags={(): null => null}
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
