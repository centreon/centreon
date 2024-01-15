import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Image, IconButton, LoadingSkeleton } from '@centreon/ui';

import { useStyles } from './DisplayType.styles';

enum DisplayType {
  All = 'all',
  Host = 'host',
  Service = 'service'
}
interface Props {
  disabled: boolean;
  displayType: DisplayType;
  iconPath: string;
  id: DisplayType;
  name: string;
  selectDisplayType: () => void;
}

const Action = ({
  iconPath,
  name,
  displayType,
  disabled,
  selectDisplayType,
  id
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={t(name) as string}
      className={classes.item}
      data-is-active={equals(displayType, id)}
      data-testid={name}
      disabled={disabled}
      title={t(name) as string}
      onClick={selectDisplayType}
    >
      <Image
        alt={name}
        fallback={<LoadingSkeleton height={42} width={42} />}
        imagePath={iconPath}
      />
    </IconButton>
  );
};

export default Action;
