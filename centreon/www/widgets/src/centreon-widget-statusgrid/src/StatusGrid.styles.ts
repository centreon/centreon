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
    maxHeight: theme.spacing(22),
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  dateContainer: {
    padding: theme.spacing(1, 1, 0)
  },
  dot: {
    borderRadius: '50%',
    height: theme.spacing(0.5),
    width: theme.spacing(0.5)
  },
  header: {
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    display: 'flex',
    justifyContent: 'center',
    padding: theme.spacing(1, 0),
    width: '100%'
  },
  name: {
    padding: theme.spacing(0, 1)
  },
  parent: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  servicesContainer: {
    padding: theme.spacing(0, 1, 1),
    textAlign: 'start'
  }
}));
