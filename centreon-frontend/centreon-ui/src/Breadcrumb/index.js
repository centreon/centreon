import React, {Component} from "react";
import { makeStyles } from "@material-ui/core/styles";
import Paper from "@material-ui/core/Paper";
import Breadcrumbs from "@material-ui/core/Breadcrumbs";
import Typography from "@material-ui/core/Typography";
import Link from "@material-ui/core/Link";
import NavigateNextIcon from "@material-ui/icons/NavigateNext";

const useStyles = makeStyles(theme => ({
  root: {
    justifyContent: "center",
    flexWrap: "wrap"
  },
  paper: {
    padding: theme.spacing(1, 2)
  },
  item: {
    fontSize: "12px"
  }
}));


function Breadcrumb( props ) {
    const classes = useStyles();
    const {breadcrumbs} = props;
    return (
      <div className={classes.root}>
        <Paper elevation={0} className={classes.paper}>
          <Breadcrumbs
            separator={<NavigateNextIcon fontSize="small" />}
            aria-label="Breadcrumb"
          >
            {breadcrumbs ? breadcrumbs.map((breadcrumb, index) =>
              index === breadcrumbs.length-1 ? (
                <Link
                  color="inherit"
                  className={classes.item}
                  href={breadcrumb.link}
                >
                  {breadcrumb.label}
                </Link>
              ) : (
                <Typography className={classes.item} color="textPrimary">
                  {breadcrumb.label}
                </Typography>
              )
            ) : null}
          </Breadcrumbs>
        </Paper>
      </div>
    );
}

export default Breadcrumb;
