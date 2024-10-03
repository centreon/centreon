import { makeStyles } from 'tss-react/mui';

export const useTileStyles = makeStyles()((theme) => ({
  container: {
    cursor: 'pointer',
    height: '100%',
    paddingTop: theme.spacing(4.5),
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
  baParent: {
    paddingBottom: theme.spacing(0.5)
  },
  baParentText: {
    paddingBottom: theme.spacing(0.5)
  },
  body: {
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  boleanRuleLink: {
    color: theme.palette.primary.main
  },
  boleanRuleLinkWrapper: {
    marginLeft: theme.spacing(0.5)
  },
  boleanRulebody: {
    paddingBottom: theme.spacing(1)
  },
  container: {
    minWidth: theme.spacing(30)
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
    justifyContent: 'space-between',
    width: theme.spacing(8)
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
  metricName: {
    maxWidth: theme.spacing(18),
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
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
