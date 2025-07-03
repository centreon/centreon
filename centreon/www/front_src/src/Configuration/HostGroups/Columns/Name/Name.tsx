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

  const name = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: truncate({ content: row.name, maxLength: 50 })
  });

  return (
    <div className={classes.container}>
      {row?.icon && (
        <img alt={row.icon.name} src={row.icon.url} height={16} width={16} />
      )}
      {name}
    </div>
  );
};

export default Name;
