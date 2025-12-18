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
    isRowDisabled: row.isActivated
  });

  const renderedName =
    renderEllipsisTypography?.({
      className: classes.resourceNameText,
      formattedString: truncate({ content: row.name, maxLength: 50 })
    }) || row.name;

  return <div className={classes.container}>{renderedName}</div>;
};

export default Name;
