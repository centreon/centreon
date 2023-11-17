import { makeStyles } from 'tss-react/mui';

export const useTileStyles = makeStyles()((theme) => ({
  container: {
    paddingTop: theme.spacing(1.5),
    width: '100%'
  },
  resourceName: {
    fontWeight: theme.typography.fontWeightMedium
  }
}));

export const useNoResourcesStyles = makeStyles()({
  noDataFound: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  }
});

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
