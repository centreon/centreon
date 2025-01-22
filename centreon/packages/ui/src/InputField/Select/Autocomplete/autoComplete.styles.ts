import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { autocompleteClasses } from '@mui/material/Autocomplete';

import { ThemeMode } from '@centreon/ui-context';

interface StyledProps {
  hideInput?: boolean;
}

const textfieldHeight = (hideInput?: boolean): number | undefined =>
  hideInput ? 0 : undefined;

export const useAutoCompleteStyles = makeStyles<StyledProps>()(
  (theme, { hideInput }) => ({
    hiddenText: {
      transform: 'scale(0)'
    },
    input: {
      '&:after': {
        borderBottom: 0
      },
      '&:before': {
        borderBottom: 0,
        content: 'unset'
      },
      '&:hover:before': {
        borderBottom: 0
      },
      height: textfieldHeight(hideInput)
    },
    inputLabel: {
      '&&': {
        fontSize: theme.typography.body1.fontSize,
        maxWidth: '72%',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        transform: 'translate(12px, 14px) scale(1)',
        whiteSpace: 'nowrap'
      }
    },
    inputLabelShrink: {
      '&&': {
        maxWidth: '90%'
      }
    },
    inputWithLabel: {
      '&[class*="MuiFilledInput-root"]': {
        paddingTop: theme.spacing(2)
      },
      paddingTop: theme.spacing(1)
    },
    inputWithoutLabel: {
      '&[class*="MuiFilledInput-root"][class*="MuiFilledInput-marginDense"]': {
        paddingBottom: hideInput ? 0 : theme.spacing(0.75),
        paddingRight: hideInput ? 0 : theme.spacing(1),
        paddingTop: hideInput ? 0 : theme.spacing(0.75)
      }
    },
    loadingIndicator: {
      textAlign: 'center'
    },
    options: {
      alignItems: 'center',
      display: 'grid',
      gridAutoFlow: 'column',
      gridGap: theme.spacing(1)
    },
    popper: {
      [`& .${autocompleteClasses.listbox}`]: {
        [`& .${autocompleteClasses.option}`]: {
          [`&:hover, &[aria-selected="true"], &.${autocompleteClasses.focused},
        &.${autocompleteClasses.focused}[aria-selected="true"]`]: {
            background: equals(theme.palette.mode, ThemeMode.dark)
              ? theme.palette.primary.dark
              : theme.palette.primary.light,
            color: equals(theme.palette.mode, ThemeMode.dark)
              ? theme.palette.common.white
              : theme.palette.primary.main
          }
        },
        padding: 0
      },
      zIndex: theme.zIndex.tooltip + 1
    },
    textfield: {
      height: textfieldHeight(hideInput),
      visibility: hideInput ? 'hidden' : 'visible'
    }
  })
);
