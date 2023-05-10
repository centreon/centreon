import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {
    '&[data-variant="primary"]:not(:disabled)': {
      backgroundColor: theme.palette.primary.main
    },

    '&[data-variant="secondary"]:not(:disabled)': {
      borderColor: theme.palette.primary.main,
      color: theme.palette.primary.main
    }
  }
}));

export { useStyles };
