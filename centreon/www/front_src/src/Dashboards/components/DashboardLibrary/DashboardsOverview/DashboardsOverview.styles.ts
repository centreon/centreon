import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    height: `calc(100vh - ${theme.spacing(20)})`,
    overflowY: 'auto',
    width: '100%'
  }
}));
