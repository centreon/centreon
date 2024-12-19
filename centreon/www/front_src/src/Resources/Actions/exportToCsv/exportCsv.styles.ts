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
    flex: 0.5
  },
  spacing: {
    height: theme.spacing(2)
  }
}));

export default useExportCsvStyles;
