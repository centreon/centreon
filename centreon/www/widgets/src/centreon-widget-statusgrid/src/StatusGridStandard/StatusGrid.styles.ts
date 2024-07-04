import { makeStyles } from 'tss-react/mui';

export const useTileStyles = makeStyles()((theme) => ({
  container: {
    cursor: 'pointer',
    height: '100%',
    paddingTop: theme.spacing(4.5),
    position: 'relative',
    width: '100%'
  },
  link: {
    all: 'unset',
    display: 'block',
    height: '100%'
  },
  resourceName: {
    fontWeight: theme.typography.fontWeightMedium
  },
  resourceTypeIcon: {
    position: 'absolute',
    right: 8,
    top: 4
  },
  seeMoreContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  stateContent: {
    display: 'flex',
    flexDirection: 'row',
    height: '100%',
    marginTop: theme.spacing(0.5),
    width: '100%'
  },
  stateIcon: {
    marginLeft: theme.spacing(0.5)
  },
  statusTile: {
    '&[data-mode="compact"]': {
      height: theme.spacing(2),
      width: '100%'
    },
    borderRadius: theme.shape.borderRadius,
    height: theme.spacing(3.5),
    position: 'absolute',
    right: '0%',
    top: '0%',
    width: '100%'
  }
}));

export const useHostTooltipContentStyles = makeStyles()((theme) => ({
  body: {
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  dateContainer: {
    padding: theme.spacing(1, 1, 0)
  },
  dot: {
    borderRadius: '50%',
    height: theme.spacing(1),
    width: theme.spacing(1)
  },
  header: {
    alignItems: 'center',
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    width: '100%'
  },
  listContainer: {
    maxHeight: theme.spacing(20),
    overflowY: 'auto',
    padding: theme.spacing(0, 1, 1),
    textAlign: 'start'
  },
  listHeader: {
    backgroundColor: theme.palette.background.paper,
    position: 'sticky',
    top: 0,
    width: '100%'
  },
  metric: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    justifyContent: 'space-between'
  },
  parent: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
