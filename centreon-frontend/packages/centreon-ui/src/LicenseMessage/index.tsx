import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { alpha, makeStyles, Paper, Typography } from '@material-ui/core';

import { labelInvalidLicense } from './translatedLabels';

const useStyles = makeStyles((theme) => ({
  divWrapper: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
  },
  paper: {
    backgroundColor: alpha(theme.palette.error.main, 0.15),
    marginTop: theme.spacing(8),
    textAlign: 'center',
    width: '400px',
  },
}));

interface Props {
  label?: string;
}

const LicenseMessage = ({ label }: Props): JSX.Element => {
  const { t } = useTranslation();
  const classes = useStyles();

  return (
    <div className={classes.divWrapper}>
      <Paper className={classes.paper}>
        <Typography component="p">{label || t(labelInvalidLicense)}</Typography>
      </Paper>
    </div>
  );
};

export default LicenseMessage;
