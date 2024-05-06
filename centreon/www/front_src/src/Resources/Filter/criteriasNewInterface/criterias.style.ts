import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  bridge: {
    borderStyle: 'dashed',
    margin: theme.spacing(0, 2)
  },
  containerFilter: {
    width: '100%'
  },
  div: {
    marginTop: theme.spacing(1)
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
    width: theme.spacing(80)
  },
  footer: {
    borderStyle: 'dashed',
    margin: theme.spacing(1, 0, 0, 0)
  },
  inputInformation: {
    backgroundColor: theme.palette.background.default,
    minWidth: theme.spacing(38)
  },
  moreFiltersButton: {
    alignSelf: 'flex-end',
    display: 'flex',
    marginBottom: theme.spacing(1)
  },
  small: {
    display: 'flex',
    flexDirection: 'row',
    width: theme.spacing(40)
  }
}));
