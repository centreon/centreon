import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Paper from '@material-ui/core/Paper';
import Breadcrumbs from '@material-ui/core/Breadcrumbs';
import Typography from '@material-ui/core/Typography';
import Link from '@material-ui/core/Link';
import NavigateNextIcon from '@material-ui/icons/NavigateNext';

const useStyles = makeStyles(theme => ({
  root: {
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  paper: {
    padding: theme.spacing(1, 2),
  },
  item: {
    fontSize: '12px'
  }
}));

function handleClick(event) {
  event.preventDefault();
  alert('You clicked a breadcrumb.');
}

function Breadcrumb() {
  const classes = useStyles();

  return (
    <div className={classes.root}>
      <Paper elevation={0} className={classes.paper}>
        <Breadcrumbs separator={<NavigateNextIcon fontSize="small" />} aria-label="Breadcrumb">
          <Link color="inherit" className={classes.item} href="/" onClick={handleClick}>
            Configuration
          </Link>
          <Link color="inherit" className={classes.item} href="/getting-started/installation/" onClick={handleClick}>
            Business Activity
          </Link>
          <Typography className={classes.item} color="textPrimary">Activities</Typography>
        </Breadcrumbs>
      </Paper>
      
    </div>
  );
}

export default Breadcrumb;