import { JSX } from 'react';

import { type ComponentColumnProps, truncate } from '@centreon/ui';
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

  if (row?.internalListingParentId) {
    return <div />;
  }

  const name =
    renderEllipsisTypography?.({
      className: classes.resourceNameText,
      formattedString: truncate({ content: row.name, maxLength: 50 })
    }) || row.name;

  return <div className={classes.container}>{name}</div>;
};

export default Name;
