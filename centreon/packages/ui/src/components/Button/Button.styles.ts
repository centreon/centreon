import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {
    '&[data-size="small"]': {
      paddingLeft: theme.spacing(2),
      paddingRight: theme.spacing(2)
    },

    '&[data-variant="primary"]:not(:disabled)': {
      '&[data-is-danger="true"]': {
        backgroundColor: theme.palette.error.main
      },

      backgroundColor: theme.palette.primary.main
    },

    '&[data-variant="secondary"]:not(:disabled)': {
      '&[data-is-danger="true"]': {
        borderColor: theme.palette.error.main,
        color: theme.palette.error.main
      },

      borderColor: theme.palette.primary.main,
      color: theme.palette.primary.main
    }
  }
}));

export { useStyles };
