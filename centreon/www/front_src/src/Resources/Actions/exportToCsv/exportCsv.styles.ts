import { makeStyles } from 'tss-react/mui';

const useExportCsvStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  error: {
    color: theme.palette.error.main
  },
  information: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    flex: 0.6,
    flexDirection: 'column',
    justifyContent: 'space-between',
    padding: theme.spacing(1)
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  },
  lines: {
    fontWeight: 'bold'
  },
  radioButtonsContainer: {
    display: 'flex',
    flex: 0.4,
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  radioInput: {
    marginLeft: theme.spacing(0.5),
    padding: theme.spacing(0.5)
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'row'
  },
  subTitle: {
    color: theme.palette.text.primary,
    paddingBottom: 0.5
  }
}));

export default useExportCsvStyles;
