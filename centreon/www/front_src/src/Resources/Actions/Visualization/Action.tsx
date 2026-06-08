// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { IconButton, Image } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { Visualization } from '../../models';
import useIconPath from './useIconPath';
import useVisualization from './useVisualization';
import { useStyles } from './Visualization.styles';

interface Props {
  IconOnActive: string;
  IconOnActiveDark: string;
  IconOnInactive: string;
  IconOnInactiveDark: string;
  title: string;
  type: Visualization;
}

const Action = ({
  IconOnActive,
  IconOnActiveDark,
  IconOnInactive,
  IconOnInactiveDark,
  title,
  type
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const imagePath = useIconPath({
    IconOnActive,
    IconOnActiveDark,
    IconOnInactive,
    IconOnInactiveDark,
    type
  });
  const { selectVisualization } = useVisualization({ type });

  return (
    <IconButton
      ariaLabel={t(title) as string}
      className={classes.iconButton}
      data-testid={title}
      onClick={selectVisualization}
      title={t(title) as string}
      tooltipClassName={classes.tooltipClassName}
    >
      <Image alt={title} imagePath={imagePath} />
    </IconButton>
  );
};

export default Action;
