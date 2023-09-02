import { useTranslation } from 'react-i18next';

import { Image, IconButton } from '@centreon/ui';

import { Visualization } from '../../models';

import useIconPath from './useIconPath';
import useVisualization from './useVisualization';

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
  const { t } = useTranslation();

  const imagePath = useIconPath({ IconOnActive, IconOnInactive, type });
  const { selectVisualization } = useVisualization({ type });

  return (
    <IconButton
      ariaLabel={t(title)}
      data-testid={title}
      title={t(title)}
      onClick={selectVisualization}
    >
      <Image alt={title} imagePath={imagePath} />
    </IconButton>
  );
};

export default Action;
