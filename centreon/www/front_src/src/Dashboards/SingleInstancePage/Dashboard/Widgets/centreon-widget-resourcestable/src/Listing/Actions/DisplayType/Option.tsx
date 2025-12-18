import { IconButton } from '@centreon/ui';

import { DisplayType } from '../../models';
import { useStyles } from './displayType.styles';
import Icon from './Icons';

interface Props {
  changeDisplayType: () => void;
  disabled: boolean;
  isActive: boolean;
  option: DisplayType;
  title: string;
}

const Option = ({
  title,
  option,
  changeDisplayType,
  disabled,
  isActive
}: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <IconButton
      ariaLabel={title}
      className={classes.iconButton}
      disabled={disabled}
      onClick={changeDisplayType}
      title={title}
      tooltipClassName={classes.tooltipClassName}
    >
      <Icon disabled={disabled} displayType={option} isActive={isActive} />
    </IconButton>
  );
};

export default Option;
