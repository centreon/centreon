import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  list: {
    '&[data-is-empty="true"]': {
      display: 'flex',
      justifyContent: 'center',
      width: '100%'
    },
    '&[data-variant="grid"]': {
      '& > *': {
        width: 'auto'
      },
      display: 'grid',
      gridGap: theme.spacing(2),
      gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))'
    },
    display: 'flex'
  }
}));

export { useStyles };
