import { makeStyles } from 'tss-react/mui';

export const useTiledListPageStyles = makeStyles()((theme) => ({
  listPage: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    paddingBottom: theme.spacing(1.5)
  }
}));

export const useTiledListingActionsStyles = makeStyles()((theme) => ({
  actions: {
    paddingBottom: theme.spacing(2.5)
  }
}));
