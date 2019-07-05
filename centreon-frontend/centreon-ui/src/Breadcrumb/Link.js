import React from 'react';
import Typography from '@material-ui/core/Typography';
import Link from '@material-ui/core/Link';

const BreadcrumbLink = ({ index, count, breadcrumb, classes }) => {
  return index !== count - 1 ? (
    <Link color="inherit" className={classes.item} href={breadcrumb.link}>
      {breadcrumb.label}
    </Link>
  ) : (
    <Typography className={classes.item} color="textPrimary">
      {breadcrumb.label}
    </Typography>
  );
};

export default BreadcrumbLink;
