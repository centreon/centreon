import { orange } from '@mui/material/colors';
import { makeStyles } from 'tss-react/mui';

const useExportCsvStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column'
  },
  label: {
    paddingLeft: theme.spacing(0.25)
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'row'
  },
  checkBoxContainer: {
    flex: 0.4
  },
  spacing: {
    height: theme.spacing(2)
  },
  information: {
    background: '#EDEDED',
    flex: 0.6,
    borderRadius: 8,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    justifyContent: 'space-between'
  },
  warning: {
    width: '100%',
    backgroundColor: orange[100],
    padding: theme.spacing(1)
  },
  lines: {
    fontWeight: 'bold'
  },
  error: {
    color: theme.palette.error.main
  }
}));

export default useExportCsvStyles;
