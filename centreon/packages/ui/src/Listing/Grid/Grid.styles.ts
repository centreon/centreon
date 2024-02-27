import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  grid: {
    '&[data-variant="grid"]': {
      '& > *': {
        width: 'auto'
      },
      display: 'grid',
      gridGap: theme.spacing(2.5),
      gridTemplateColumns: `repeat(auto-fill, ${theme.spacing(45)})`
    },
    '&[data-variant="listing"]': {
      height: '100%'
    },
    '&[data-variant][data-is-empty="true"]': {
      display: 'flex',
      justifyContent: 'center',
      width: '100%'
    },
    display: 'flex'
  },
  gridScrollContainer: {
    height: '100%',
    overflowY: 'auto'
  }
}));

export { useStyles };
