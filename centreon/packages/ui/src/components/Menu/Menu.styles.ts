import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  menuDivider: {
    '&, &.MuiDivider-root': {
      borderColor: theme.palette.menu.divider.border
    }
  },
  menuItem: {
    '&, &.MuiMenuItem-root': {
      '& > button': {
        '&:hover:not(&[data-is-active="true"])': {
          backgroundColor: theme.palette.menu.item.background.hover,
          color: theme.palette.menu.item.color.hover
        },
        backgroundColor: theme.palette.menu.item.background.default,
        color: theme.palette.menu.item.color.default,
        fontSize: '1rem',
        justifyContent: 'flex-start',
        width: '100%'
      },

      '&:has(button)': {
        '& > .MuiTouchRipple-root': {
          display: 'none'
        },
        '&:hover:not(&[data-is-active="true"])': {
          backgroundColor: 'unset'
        },
        padding: theme.spacing(0, 2)
      },

      '&:not(:has(button))': {
        '&:hover:not(&[data-is-active="true"])': {
          backgroundColor: theme.palette.menu.item.background.hover,
          color: theme.palette.menu.item.color.hover
        },
        '&[data-is-active="true"], &.Mui-selected': {
          '&[data-is-disabled="true"], &.Mui-disabled': {
            opacity: 1
          },
          backgroundColor: theme.palette.menu.item.background.active,
          color: theme.palette.menu.item.color.active,
          opacity: 1
        },
        '&[data-is-disabled="true"], &.Mui-disabled': {
          opacity: 0.5
        },
        backgroundColor: theme.palette.menu.item.background.default,
        color: theme.palette.menu.item.color.default,
        padding: theme.spacing(0.75, 2)
      },

      fontSize: '1rem',
      minHeight: 'unset'
    }
  },
  menuItems: {
    '& > .MuiPaper-root.MuiMenu-paper': {
      backgroundColor: theme.palette.menu.background,
      borderRadius: '4px',
      boxShadow: theme.shadows[8],
      minWidth: '240px',
      transform: `translateY(${theme.spacing(0.5)}) !important`
    },
    '& ul, & ul.MuiMenu-list': {
      padding: theme.spacing(1, 0)
    }
  }
}));
