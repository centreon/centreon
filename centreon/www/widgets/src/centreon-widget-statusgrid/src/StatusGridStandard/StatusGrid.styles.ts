import { makeStyles } from 'tss-react/mui';

export const useTileStyles = makeStyles()((theme) => ({
  container: {
    cursor: 'pointer',
    height: '100%',
    position: 'relative',
    width: '100%'
  },
  icon: {
    fontSize: theme.spacing(2)
  },
  iconContainer: {
    alignItems: 'center',
    display: 'flex',
    height: theme.spacing(1.5),
    justifyContent: 'end',
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
  seeMoreContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  statusTile: {
    '&[data-mode="compact"]': {
      height: theme.spacing(1.25),
      width: theme.spacing(1.25)
    },
    border: `1px solid ${theme.palette.text.primary}`,
    borderRadius: theme.shape.borderRadius,
    height: theme.spacing(1.5),
    position: 'absolute',
    right: '10%',
    top: '10%',
    width: theme.spacing(1.5)
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
  impact: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  link: {
    '&:hover': {
      cursor: 'pointer',
      textDecoration: 'underline'
    },
    all: 'unset',
    color: theme.palette.primary.main,
    display: 'block',
    fontWeight: theme.typography.fontWeightBold,
    height: '100%',
    marginTop: theme.spacing(2),
    textAlign: 'center',
    width: '100%'
  },
  listContainer: {
    maxHeight: theme.spacing(28),
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
  },
  statusInformation: {
    display: 'flex',
    justifyContent: 'center',
    width: '100%'
  },
  threshold: {
    display: 'flex',
    justifyContent: 'space-between',
    width: '100%'
  },
  thresholdContainer: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  }
}));
