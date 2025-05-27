import { type ComponentColumnProps, truncate } from '@centreon/ui';
import { JSX } from 'react';
import useNameStyles from './Name.style';

const Name = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useNameStyles({
    isRowDisabled: row.isActivated,
    isHovered
  });

  const name = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: truncate({ content: row.name, maxLength: 50 })
  });

  return <div className={classes.container}>{name}</div>;
};

export default Name;
