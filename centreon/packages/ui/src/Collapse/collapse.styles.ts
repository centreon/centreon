import { makeStyles } from 'tss-react/mui';

export const useCollapseStyles = makeStyles()((theme) => ({
  container: {
    backgroundColor: theme.palette.background.listingHeader,
    color: theme.palette.common.white,
    '&:hover': {
      backgroundColor: theme.palette.background.listingHeader,
      color: theme.palette.common.white
    }
  }
}));
