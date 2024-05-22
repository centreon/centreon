import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Image, IconButton } from '@centreon/ui';

import { DisplayType } from '../models';

import { useStyles } from './displayType.styles';

interface Props {
  IconOnActive: string;
  IconOnInactive: string;
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
  setPanelOptions
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const changeDisplayType = (): void => {
    setPanelOptions?.({ displayType: option });
  };

  const imagePath = equals(displayType, option) ? IconOnActive : IconOnInactive;

  return (
    <IconButton
      ariaLabel={title}
      className={classes.iconButton}
      title={t(title)}
      tooltipClassName={classes.tooltipClassName}
      onClick={changeDisplayType}
    >
      <Image imagePath={imagePath} />
    </IconButton>
  );
};

export default Option;
