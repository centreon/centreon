import { makeStyles } from 'tss-react/mui';

export const useStatusGridCondensedStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyContent: 'center',
    width: '100%',
    height: '100%'
  },
  count: {
    height: '35%',
    width: '100%'
  },
  countParentSize: {
    display: 'flex',
    alignItems: 'flex-end',
    justifyContent: 'center'
  },
  countText: {
    fontWeight: theme.typography.fontWeightBold,
    textAlign: 'center',
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
    width: '35%'
  },
  labelText: {
    fontWeight: theme.typography.fontWeightMedium,
    lineHeight: 1
  },
  labelTextContainer: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
    maxHeight: '50px'
  },
  link: {
    color: 'inherit',
    textDecoration: 'none'
  },
  status: {
    borderRadius: theme.shape.borderRadius,
    height: '100%',
    minHeight: '70px'
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
    height: '100%',
    width: '100%'
  },
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative'
  }
}));
