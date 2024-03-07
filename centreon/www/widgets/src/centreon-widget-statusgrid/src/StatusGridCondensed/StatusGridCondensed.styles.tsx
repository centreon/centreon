import { makeStyles } from 'tss-react/mui';

export const useStatusGridCondensedStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  count: {
    height: '32%',
    textAlign: 'center',
    width: '85%'
  },
  countText: {
    fontWeight: theme.typography.fontWeightBold,
    lineHeight: 1
  },
  countTextContainer: {
    alignItems: 'flex-end',
    display: 'flex',
    justifyContent: 'center'
  },
  label: {
    height: '20%',
    textAlign: 'center',
    width: '65%'
  },
  labelText: {
    fontWeight: theme.typography.fontWeightMedium,
    lineHeight: 1
  },
  link: {
    color: 'inherit',
    textDecoration: 'none'
  },
  status: {
    aspectRatio: '30 / 9',
    borderRadius: theme.shape.borderRadius
  },
  statusCard: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center'
  },
  statuses: {
    display: 'grid',
    gap: theme.spacing(1),
    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
    width: '100%'
  },
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative'
  }
}));
