import { makeStyles } from 'tss-react/mui';

const useFavoriteFilterStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flex: 1,
    justifyContent: 'center'
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  }
}));

export default useFavoriteFilterStyles;
