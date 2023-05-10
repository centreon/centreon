import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
    dialog: {
      '& .MuiDialog-paper': {
        padding: theme.spacing(2),
        minWidth: '400px',
      }

    }

  }))
;

export { useStyles };
