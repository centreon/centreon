import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  list: {

    '&[data-variant="grid"]': {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
      gridGap: '20px',

      '& > *': {
        width: 'auto'
      }
    }
  }

}));

export { useStyles };
