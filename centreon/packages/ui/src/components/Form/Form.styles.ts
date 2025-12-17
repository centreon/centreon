import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    '&, & > span': {
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(2)
    },
    '&:has( > span)': {
      justifyContent: 'space-between'
    },
    justifyContent: 'flex-end'
  }
}));

export { useStyles };
