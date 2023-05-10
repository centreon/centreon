import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  list: {
    display: 'flex',

    '&[data-variant="grid"]': {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
      gridGap: '20px',

      '& > *': {
        width: 'auto'
      }
    },

    '&[data-is-empty="true"]': {
      display: 'flex',
      justifyContent: 'center',

      width: '100%',
    }
  }

}));

export { useStyles };
