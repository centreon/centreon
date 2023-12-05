import { makeStyles } from 'tss-react/mui';

import { lighten } from '@mui/system';

export const useFooterStyles = makeStyles()((theme) => ({
  footer: {
    alignItems: 'flex-start',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(3),
    height: '100%',
    width: '100%'
  },
  footerContainer: {
    backgroundColor: lighten(theme.palette.common.black, 0.295),
    border: 'none',
    borderRadius: 0,
    bottom: 0,
    color: theme.palette.common.white,
    height: theme.spacing(15),
    overflow: 'hidden',
    position: 'fixed',
    width: '100%'
  }
}));

export const usePlayerStyles = makeStyles()((theme) => ({
  icon: {
    '&[data-size="large"]': {
      height: theme.spacing(5),
      width: theme.spacing(5)
    },
    color: theme.palette.common.white,
    height: theme.spacing(3),
    width: theme.spacing(3)
  },
  player: {
    display: 'flex',
    flexDirection: 'row',
    marginLeft: theme.spacing(5),
    marginTop: theme.spacing(3)
  }
}));

export const useDashboardsStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    marginTop: theme.spacing(2),
    width: '75vw'
  },
  dashboard: {
    '&[data-selected="true"]': {
      backgroundColor: lighten(theme.palette.common.black, 0.5)
    },
    alignItems: 'center',
    backgroundColor: lighten(theme.palette.common.black, 0.35),
    borderRadius: theme.shape.borderRadius,
    cursor: 'pointer',
    display: 'flex',
    height: theme.spacing(8),
    minHeight: theme.spacing(8),
    minWidth: theme.spacing(13),
    padding: theme.spacing(0.5),
    width: theme.spacing(13)
  },
  dashboards: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2.5),
    overflowX: 'auto',
    paddingBottom: theme.spacing(0.5),
    width: '100%'
  },
  icon: {
    color: theme.palette.common.white,
    height: theme.spacing(3),
    width: theme.spacing(3)
  },
  text: {
    textAlign: 'center'
  }
}));
