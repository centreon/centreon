import { makeStyles } from 'tss-react/mui';

export const useCollapsibleItemStyles = makeStyles()((theme) => ({
  accordion: {
    backgroundColor: 'transparent',
    border: 'none',
    borderBottom: `1px solid ${theme.palette.divider}`,
    width: '100%'
  },
  accordionDetails: {
    padding: theme.spacing(0, 2, 2)
  },
  accordionDetailsCompact: {
    padding: theme.spacing(0)
  },
  accordionSummary: {
    margin: theme.spacing(1.5, 0)
  },
  accordionSummaryCompactContent: {
    margin: theme.spacing(0, 0, 0.5)
  },
  accordionSummaryCompactRoot: {
    minHeight: theme.spacing(1),
    width: '100%'
  },
  accordionSummaryRoot: {
    width: '100%'
  },
  customTitle: {
    whiteSpace: 'nowrap'
  },
  summaryContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    width: '100%'
  }
}));
