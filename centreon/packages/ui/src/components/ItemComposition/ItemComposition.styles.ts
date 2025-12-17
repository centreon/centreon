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
    width: '100%'
  },
  itemCompositionItems: {
    alignItems: 'flex-start',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    width: '100%'
  },
  itemCompositionItemsAndLink: {
    display: 'flex',
    flexDirection: 'row-reverse',
    gap: theme.spacing(0.5),
    width: '100%'
  },
  linkIcon: {
    backgroundColor: theme.palette.background.paper,
    padding: theme.spacing(0.5, 0),
    transform: `rotate3d(0, 0, 1, 90deg) translate3d(0, ${theme.spacing(
      1.6
    )}, 0)`
  },
  linkedItems: {
    alignItems: 'center',
    border: `1px solid ${theme.palette.divider}`,
    borderRight: 'none',
    display: 'flex',
    margin: theme.spacing(2, 0, 2, 1),
    minHeight: '100%',
    position: 'relative',
    width: theme.spacing(1)
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
    display: 'grid',
    gridAutoFlow: 'column',
    width: '100%'
  },
  visibilityHiden: {
    display: 'none'
  }
}));
