import { ComponentColumnProps } from '@centreon/ui';
import { truncateString } from '../../utils';
import { useNameStyles } from './Name.styles';

const Name = ({
  row,
  renderEllipsisTypography,
  isHovered
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useNameStyles({ isHovered });

  const icon = row?.icon?.url;

  const name = renderEllipsisTypography?.({
    className: classes.resourceNameText,
    formattedString: truncateString(row.name)
  });

  return (
    <div className={classes.container}>
      {icon && <img alt="icon" height={16} src={icon} width={16} />}
      {name}
    </div>
  );
};

export default Name;
