import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  dialog: {
    '& .MuiDialog-paper': {
      minWidth: '400px',
      padding: theme.spacing(2)
    }
  }
}));
export { useStyles };
