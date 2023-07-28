import { makeStyles } from 'tss-react/mui';

export const useItemCompositionStyles = makeStyles()((theme) => ({
  itemCompositionContainer: {
    alignItems: 'flex-start',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  }
}));

export const useItemStyles = makeStyles()((theme) => ({
  itemContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  itemContent: {
    display: 'flex',
    flexDirection: 'row'
  }
}));
