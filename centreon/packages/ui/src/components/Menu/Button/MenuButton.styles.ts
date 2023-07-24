import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonIcon: {
    '[data-is-active="true"] &': {
      transform: 'rotate(180deg)'
    },

    fontSize: '1.25rem',
    transition: 'transform 0.15s ease-in-out'
  },
  menuButton: {
    '&:hover:not(&[data-is-active="true"])': {
      backgroundColor: theme.palette.menu.button.background.hover,
      color: theme.palette.menu.button.color.hover
    },
    '&[data-is-active="true"]': {
      backgroundColor: theme.palette.menu.button.background.active,
      color: theme.palette.menu.button.color.active
    },
    backgroundColor: theme.palette.menu.button.background.default,
    color: theme.palette.menu.button.color.default,
    display: 'flex',
    height: 'unset',
    minWidth: 'unset'
  }
}));
