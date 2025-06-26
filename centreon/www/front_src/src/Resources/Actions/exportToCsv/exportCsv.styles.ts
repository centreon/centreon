import { makeStyles } from 'tss-react/mui';

const useExportCsvStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'row'
  },
  radioButtonsContainer: {
    flex: 0.4,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  information: {
    backgroundColor: theme.palette.background.default,
    flex: 0.6,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    justifyContent: 'space-between'
  },
  lines: {
    fontWeight: 'bold'
  },
  error: {
    color: theme.palette.error.main
  },
  radioInput: {
    padding: theme.spacing(0.5),
    marginLeft: theme.spacing(0.5)
  },
  subTitle: {
    paddingBottom: 0.5,
    color: theme.palette.text.primary
  }
}));

export default useExportCsvStyles;
