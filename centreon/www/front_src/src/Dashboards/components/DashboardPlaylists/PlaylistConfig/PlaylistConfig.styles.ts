import { makeStyles } from 'tss-react/mui';

export const usePlaylistConfigStyles = makeStyles()((theme) => ({
  rotationTime: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
