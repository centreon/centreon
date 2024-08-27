import { makeStyles } from 'tss-react/mui';

export const useAllOfResourceTypeCheckboxStyles = makeStyles()((theme) => ({
  checkbox: {
    padding: theme.spacing(0.5)
  },
  label: {
    marginLeft: theme.spacing(-0.25)
  }
}));
