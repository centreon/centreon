import { type ComponentColumnProps, truncate } from '@centreon/ui';

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

  const name = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: truncate({ content: row.name, maxLength: 50 })
  });

  return (
    <div className={classes.container}>
      {row?.icon && (
        <img alt={row.icon.name} height={16} src={row.icon.url} width={16} />
      )}
      {name}
    </div>
  );
};

export default Name;
