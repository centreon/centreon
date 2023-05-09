import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {

    '&[data-variant="primary"]:not(:disabled)': {
      backgroundColor: theme.palette.primary.main
    },

    '&[data-variant="secondary"]:not(:disabled)': {
      color: theme.palette.primary.main,
      borderColor: theme.palette.primary.main
    }

  }

}));

export { useStyles };
