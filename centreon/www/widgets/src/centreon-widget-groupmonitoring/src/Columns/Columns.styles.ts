import { makeStyles } from 'tss-react/mui';

export const useStatusesColumnStyles = makeStyles()((theme) => ({
  container: { display: 'flex', flexDirection: 'row', gap: '16px' },
  count: {
    width: theme.spacing(9.5)
  },
  status: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: '4px',
    justifyContent: 'center'
  },
  statusLabel: {
    lineHeight: 1
  },
  statusLabelContainer: {
    borderRadius: '50%',
    height: '16px',
    justifyContent: 'center',
    width: '16px'
  }
}));
