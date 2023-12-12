import { makeStyles } from 'tss-react/mui';

import { styled, Switch as MUISwitch } from '@mui/material';

export const useColumnStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  contactGroups: {
    marginLeft: theme.spacing(0.5)
  },
  icon: {
    fontSize: theme.spacing(2)
  },
  line: {
    fontSize: theme.spacing(3),
    marginLeft: theme.spacing(0.5)
  },
  name: {
    color: 'inherit',
    textDecoration: 'none'
  }
}));
