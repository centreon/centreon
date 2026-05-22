import { type ComponentColumnProps, truncate } from '@centreon/ui';

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

  const name = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: truncate({ content: row.name as string, maxLength: 50 })
  });

  const icon = row?.icon as { name: string; url: string } | undefined;

  return (
    <div className={classes.container}>
      {icon && <img alt={icon.name} height={16} src={icon.url} width={16} />}
      {name}
    </div>
  );
};

export default Name;
