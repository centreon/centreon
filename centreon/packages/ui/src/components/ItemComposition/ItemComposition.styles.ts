import { makeStyles } from 'tss-react/mui';

export const useItemCompositionStyles = makeStyles()((theme) => ({
  buttonAndSecondaryLabel: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: '100%'
  },
  itemCompositionContainer: {
    alignItems: 'flex-start',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    width: '100%'
  }
}));

export const useItemStyles = makeStyles()((theme) => ({
  itemContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    width: '100%'
  },
  itemContent: {
    display: 'flex',
    flexDirection: 'row',
    width: '100%'
  },
  visibilityHiden: {
    display: 'none'
  }
}));
