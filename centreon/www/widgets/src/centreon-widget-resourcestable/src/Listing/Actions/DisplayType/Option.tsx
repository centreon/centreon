import { IconButton } from '@centreon/ui';

import { DisplayType } from '../../models';

import Icon from './Icons';
import { useStyles } from './displayType.styles';

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
      title={title}
      tooltipClassName={classes.tooltipClassName}
      onClick={changeDisplayType}
    >
      <Icon displayType={option} isActive={isActive} />
    </IconButton>
  );
};

export default Option;
