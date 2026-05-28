import { type ComponentColumnProps, truncate } from '@centreon/ui';

import { JSX } from 'react';

import useNameStyles from './Name.style';

const Name = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useNameStyles({
    isHovered,
    isRowDisabled: Boolean(row.isActivated)
  });

  const renderedName =
    renderEllipsisTypography?.({
      className: classes.resourceNameText,
      formattedString: truncate({ content: row.name as string, maxLength: 50 })
    }) || (row.name as string);

  return (
    <div className={classes.container}>{renderedName as React.ReactNode}</div>
  );
};

export default Name;
