import { makeStyles } from 'tss-react/mui';

export const useTilesStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
