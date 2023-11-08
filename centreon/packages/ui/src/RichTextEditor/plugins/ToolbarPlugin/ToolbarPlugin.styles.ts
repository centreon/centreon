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
  }
}));
