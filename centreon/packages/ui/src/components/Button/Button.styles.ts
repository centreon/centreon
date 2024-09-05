import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {
    '&[data-size="medium"]': {
      fontSize: '16px',
      height: 'unset',
      lineHeight: '24px'
    },

    '&[data-size="small"]': {
      '&[data-variant="primary"], &[data-variant="secondary"]': {
        paddingLeft: theme.spacing(2),
        paddingRight: theme.spacing(2)
      },
      fontSize: '14px',
      height: 'unset',
      lineHeight: '22px'
    },

    '&[data-variant="primary"]:not(:disabled)': {
      '&[data-is-danger="true"]': {
        backgroundColor: theme.palette.error.main,
        color: theme.palette.error.contrastText
      }
    },

    '&[data-variant="secondary"]:not(:disabled)': {
      '&[data-is-danger="true"]': {
        borderColor: theme.palette.error.main,
        color: theme.palette.error.main
      },

      borderColor: theme.palette.primary.main,
      color: theme.palette.primary.main
    },

    textWrap: 'noWrap',
    transition: 'unset'
  }
}));

export { useStyles };
