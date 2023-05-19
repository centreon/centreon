import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  dialogHeader: {
    '& .MuiDialogTitle-root': {
      padding: theme.spacing(0)
    },
    '& > button': {
      svg: {
        opacity: 0.6
      },
      transform: 'translate(5px, 0px)'
    },
    display: 'flex',
    gap: theme.spacing(2),

    justifyContent: 'space-between',

    padding: theme.spacing(0, 0, 2, 0)
  }
}));

export { useStyles };
