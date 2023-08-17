import { makeStyles } from 'tss-react/mui';

const useInputStyles = makeStyles()((theme) => ({
  inputDropdown: {
    '& .MuiMenuItem-root': {
      '& > .MuiTouchRipple-root': {
        display: 'none'
      },

      '&.Mui-disabled': {
        opacity: 0.5
      },
      '&.Mui-selected, &.Mui-selected:hover, &.MuiAutocomplete-option[aria-selected="true"], &.MuiAutocomplete-option[aria-selected="true"].Mui-focused':
        {
          '&.Mui-disabled': {
            opacity: 1
          },
          backgroundColor: theme.palette.menu.item.background.active,
          color: theme.palette.menu.item.color.active,
          opacity: 1
        },
      '&:hover, &.MuiAutocomplete-option.Mui-focused:not(&[aria-selected="true"])':
        {
          backgroundColor: theme.palette.menu.item.background.hover,
          color: theme.palette.menu.item.color.hover
        },
      backgroundColor: theme.palette.menu.item.background.default,
      color: theme.palette.menu.item.color.default,
      fontSize: '0.875rem',

      minHeight: 'unset',

      padding: theme.spacing(0.75, 2)
    },
    '&.MuiAutocomplete-popper > .MuiPaper-root, &.MuiPopover-root > .MuiPaper-root':
      {
        '& > ul.MuiAutocomplete-listbox, & > ul.MuiList-root': {
          padding: theme.spacing(1, 0)
        },
        backgroundColor: theme.palette.menu.background,
        borderRadius: '4px',

        boxShadow: theme.shadows[8]
      }
  }
}));

export { useInputStyles };
