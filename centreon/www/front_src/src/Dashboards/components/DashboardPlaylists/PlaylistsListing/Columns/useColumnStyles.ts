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
  copyLink: {
    marginBottom: theme.spacing(1)
  },
  icon: {
    fontSize: theme.spacing(2)
  },
  line: {
    fontSize: theme.spacing(3),
    marginLeft: theme.spacing(0.5)
  },
  linkIcon: {
    fontSize: theme.spacing(2.5)
  },
  moreActions: {
    '& .MuiDivider-root': {
      margin: theme.spacing(0.25)
    },
    paddingBottom: theme.spacing(0.25),
    paddingTop: theme.spacing(0.25)
  }
}));

export const Switch = styled(MUISwitch)(({ theme }) => ({
  '& .MuiSwitch-switchBase': {
    '&.Mui-checked': {
      '& + .MuiSwitch-track': {
        backgroundColor: theme.palette.success.main,
        opacity: 1
      },
      color: theme.palette.common.white
    }
  },
  '& .MuiSwitch-thumb': {
    backgroundColor: theme.palette.common.white
  }
}));
