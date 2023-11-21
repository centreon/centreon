import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useStyles = makeStyles()((theme) => ({
  button: {
    color: theme.palette.text.primary
  },
  buttonSelected: {
    backgroundColor: alpha(
      theme.palette.primary.main,
      theme.palette.action.activatedOpacity
    )
  },
  menu: {
    display: 'flex',
    flexDirection: 'row'
  },
  menuItem: {
    '&, &.MuiMenuItem-root': {
      fontSize: theme.typography.body2.fontSize
    }
  },
  menuItems: {
    '& > .MuiPaper-root.MuiMenu-paper': {
      minWidth: 0
    },
    '& ul, & ul.MuiMenu-list': {
      padding: 0
    }
  }
}));
