import { makeStyles } from 'tss-react/mui';

const useFavoriteFilterStyles = makeStyles()((theme) => ({
  container: {
    border: `1px solid ${theme.palette.divider}`
  },
  containerActive: {
    borderColor: theme.palette.error.main
  },
  iconActive: {
    color: theme.palette.error.main
  }
}));

export default useFavoriteFilterStyles;
