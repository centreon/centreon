import { Typography } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import ShortTypeChip from '../../ShortTypeChip';

import { useColumnStyles } from '.';

const ResourceColumn = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles({ isHovered });

  return (
    <>
      <div className={classes.resourceDetailsCell}>
        {row.icon ? (
          <img alt={row.icon.name} height={16} src={row.icon.url} width={16} />
        ) : (
          <ShortTypeChip label={row.short_type} />
        )}
      </div>
      {renderEllipsisTypography?.({
        className: classes.resourceNameText,
        formattedString: row.name
      })}
    </>
  );
};

export default ResourceColumn;
