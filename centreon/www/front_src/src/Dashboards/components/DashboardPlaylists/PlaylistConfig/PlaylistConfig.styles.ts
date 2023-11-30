import { makeStyles } from 'tss-react/mui';

export const usePlaylistConfigStyles = makeStyles()((theme) => ({
  dashboards: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  rotationTime: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  rotationTimeIcon: {
    backgroundColor: theme.palette.action.disabled,
    borderRadius: '50%',
    color: theme.palette.common.white,
    padding: theme.spacing(0.25)
  },
  selectDasbhoard: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
