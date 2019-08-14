/* eslint-disable react/jsx-indent */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable no-unused-vars */

import React, { Component } from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Paper from '@material-ui/core/Paper';
import Breadcrumbs from '@material-ui/core/Breadcrumbs';
import NavigateNextIcon from '@material-ui/icons/NavigateNext';
import BreadcrumbLink from './Link';

const useStyles = makeStyles((theme) => ({
  root: {
    justifyContent: 'center',
    flexWrap: 'wrap',
    paddingBottom: '16px'
  },
  paper: {
    padding: '0 16px',
  },
  item: {
    fontSize: '12px',
  },
}));

function Breadcrumb(props) {
  const classes = useStyles();
  const { breadcrumbs } = props;
  return (
    <div className={classes.root}>
      <Paper elevation={0} className={classes.paper}>
        <Breadcrumbs
          separator={<NavigateNextIcon fontSize="small" />}
          aria-label="Breadcrumb"
        >
          {breadcrumbs
            ? breadcrumbs.map((breadcrumb, index) => (
                <BreadcrumbLink
                  breadcrumb={breadcrumb}
                  index={index}
                  count={breadcrumbs.length}
                  classes={classes}
                />
              ))
            : null}
        </Breadcrumbs>
      </Paper>
    </div>
  );
}

export default Breadcrumb;
