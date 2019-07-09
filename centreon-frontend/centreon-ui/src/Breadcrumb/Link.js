import React from "react";
import Link from "@material-ui/core/Link";

const BreadcrumbLink = ({ index, count, breadcrumb, classes }) => {
  return index !== count - 1 ? (
    <Link color="inherit" className={classes.item} href={breadcrumb.link}>
      {breadcrumb.label}
    </Link>
  ) : (
    <Link className={classes.item} href={breadcrumb.link} color="textPrimary">
      {breadcrumb.label}
    </Link>
  );
};

export default BreadcrumbLink;
