import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  bridge: {
    borderStyle: 'dashed',
    margin: theme.spacing(0, 2)
  },
  containerFilter: {
    width: theme.spacing(75 / 2)
  },
  divider: {
    borderStyle: 'dashed',
    margin: theme.spacing(2, 0, 0, 0)
  },
  dividerInputs: {
    margin: theme.spacing(1, 0),
    opacity: 0
  },
  extended: {
    display: 'flex',
    flexDirection: 'row',
    maxWidth: theme.spacing(75)
  },
  footer: {
    borderStyle: 'dashed',
    margin: theme.spacing(1, 0, 0, 0)
  },
  formControlContainer: {
    paddingLeft: theme.spacing(1)
  },
  inputInformation: {
    backgroundColor: theme.palette.background.default,
    minWidth: theme.spacing(35)
  },
  moreFiltersButton: {
    display: 'flex',
    justifyContent: 'flex-end',
    marginBottom: theme.spacing(1)
  },
  small: {
    display: 'flex',
    flexDirection: 'row',
    maxWidth: theme.spacing(75 / 2)
  }
}));
