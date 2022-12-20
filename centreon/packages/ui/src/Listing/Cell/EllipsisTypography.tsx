import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  rowNotHovered: {
    color: theme.palette.text.secondary
  },
  text: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

interface Ellipsis {
  className?: string;
  disableRowCondition: boolean;
  formattedString: string;
  isRowHovered: boolean;
}
const EllipsisTypography = ({
  formattedString,
  isRowHovered,
  disableRowCondition,
  className
}: Ellipsis): JSX.Element => {
  const { cx, classes } = useStyles();

  return (
    <Typography
      className={cx(className, classes.text, {
        [classes.rowNotHovered]: !isRowHovered || disableRowCondition
      })}
      variant="body2"
    >
      {formattedString}
    </Typography>
  );
};

export default EllipsisTypography;
