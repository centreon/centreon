import { makeStyles } from 'tss-react/mui';

export const useAccessRightsStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(3),
    maxWidth: '520px'
  }
}));
