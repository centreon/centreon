import { useTranslation } from 'react-i18next';

import { Image, IconButton } from '@centreon/ui';

import { Visualization } from '../../models';

import useIconPath from './useIconPath';
import useVisualization from './useVisualization';
import { useStyles } from './Visualization.styles';

interface Props {
  IconOnActive: string;
  IconOnInactive: string;
  title: string;
  type: Visualization;
}

const Action = ({
  IconOnActive,
  IconOnInactive,
  title,
  type
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const imagePath = useIconPath({ IconOnActive, IconOnInactive, type });
  const { selectVisualization } = useVisualization({ type });

  return (
    <IconButton
      ariaLabel={t(title) as string}
      className={classes.iconButton}
      data-testid={title}
      title={t(title) as string}
      tooltipClassName={classes.tooltipClassName}
      onClick={selectVisualization}
    >
      <Image alt={title} imagePath={imagePath} />
    </IconButton>
  );
};

export default Action;
