import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { TableStyleAtom as TableStyle } from '../models';

interface StylesProps {
  body: TableStyle['body'];
}

const useStyles = makeStyles<StylesProps>()((theme, { body }) => ({
  rowNotHovered: {
    color: theme.palette.text.secondary
  },
  text: {
    fontSize: body.fontSize,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

interface Ellipsis {
  className?: string;
  dataStyle: TableStyle;
  disableRowCondition: boolean;
  formattedString: string;
  isRowHovered: boolean;
}
const EllipsisTypography = ({
  formattedString,
  isRowHovered,
  disableRowCondition,
  className,
  dataStyle
}: Ellipsis): JSX.Element => {
  const { cx, classes } = useStyles({ body: dataStyle.body });

  return (
    <Typography
      className={cx(className, classes.text, {
        [classes.rowNotHovered]: !isRowHovered || disableRowCondition
      })}
    >
      {formattedString}
    </Typography>
  );
};

export default EllipsisTypography;
