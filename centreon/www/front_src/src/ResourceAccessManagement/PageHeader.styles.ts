import { makeStyles } from 'tss-react/mui';

const usePageHeaderStyles = makeStyles()((theme) => ({
  box: {
    alignItems: 'center',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    gap: '10%',
    justifyContent: 'space-between',
    marginTop: theme.spacing(1)
  },
  title: {
    flex: '100%',
    fontWeight: theme.typography.fontWeightBold,
    marginTop: theme.spacing(1.5),
    paddingBottom: theme.spacing(1.5)
  }
}));

export default usePageHeaderStyles;
