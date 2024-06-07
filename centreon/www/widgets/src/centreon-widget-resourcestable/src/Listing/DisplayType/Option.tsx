import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Image, IconButton } from '@centreon/ui';

import { DisplayType } from '../models';

import { useStyles } from './displayType.styles';

interface Props {
  IconOnActive: string;
  IconOnInactive: string;
  disabled: boolean;
  displayType: DisplayType;
  option: DisplayType;
  setPanelOptions: (panelOptions) => void;
  title: string;
}

const Option = ({
  IconOnActive,
  IconOnInactive,
  title,
  option,
  displayType,
  setPanelOptions,
  disabled
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const changeDisplayType = (): void => {
    setPanelOptions?.({ displayType: option });
  };

  const imagePath =
    equals(displayType, option) && !disabled ? IconOnActive : IconOnInactive;

  return (
    <IconButton
      ariaLabel={title}
      className={classes.iconButton}
      disabled={disabled}
      title={t(title)}
      tooltipClassName={classes.tooltipClassName}
      onClick={changeDisplayType}
    >
      <Image imagePath={imagePath} />
    </IconButton>
  );
};

export default Option;
