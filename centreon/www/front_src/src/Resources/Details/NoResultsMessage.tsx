<<<<<<< HEAD
import { useTranslation } from 'react-i18next';

import { Paper, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { makeStyles, Paper, Typography } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { labelNoResultsFound } from '../translatedLabels';

const useStyles = makeStyles((theme) => ({
  container: {
    padding: theme.spacing(1),
  },
}));

const NoResultsMessage = (): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  return (
    <Paper className={classes.container}>
      <Typography align="center" variant="body1">
        {t(labelNoResultsFound)}
      </Typography>
    </Paper>
  );
};

export default NoResultsMessage;
