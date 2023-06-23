import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  dataTable: {
    '&[data-variant="grid"]': {
      '& > *': {
        width: 'auto'
      },
      display: 'grid',
      gridGap: theme.spacing(2.5),
      gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))'
    },
    '&[data-variant][data-is-empty="true"]': {
      display: 'flex',
      justifyContent: 'center',
      width: '100%'
    },
    display: 'flex'
  },
  dataTableScrollContainer: {
    // FIXME the global -webkit-scrollbar styles are not optimal for this component in grid variant
    height: '100%',
    overflowY: 'auto'
  }
}));

export { useStyles };
