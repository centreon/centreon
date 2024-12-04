import { makeStyles } from 'tss-react/mui';

const useFavoriteFilterStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex'
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  }
}));

export default useFavoriteFilterStyles;
