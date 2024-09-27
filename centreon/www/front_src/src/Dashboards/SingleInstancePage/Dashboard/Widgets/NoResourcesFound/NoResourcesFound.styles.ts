import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useStyles = makeStyles()((theme) => ({
  noDataFound: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  noDataFoundIcon: {
    alignItems: 'center',
    background: theme.palette.warning.main,
    borderRadius: '50%',
    color: theme.palette.common.white,
    display: 'flex',
    fontSize: theme.typography.h4.fontSize,
    height: theme.spacing(4),
    justifyContent: 'center',
    width: theme.spacing(4)
  },
  noDataFoundIconWrapper: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.warning.main, 0.5),
    borderRadius: '50%',
    display: 'flex',
    height: theme.spacing(6),
    justifyContent: 'center',
    width: theme.spacing(6)
  }
}));
