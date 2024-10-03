import { makeStyles } from 'tss-react/mui';

export const useStatusesColumnStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing(1)
  },
  count: {
    width: theme.spacing(9)
  },
  link: {
    color: 'inherit',
    textDecoration: 'none'
  },
  status: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(0.5),
    justifyContent: 'center'
  },
  statusLabel: {
    lineHeight: 1
  },
  statusLabelContainer: {
    borderRadius: '50%',
    height: theme.spacing(2),
    justifyContent: 'center',
    width: theme.spacing(2)
  }
}));
