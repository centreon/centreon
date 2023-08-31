import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { Image, IconButton } from '@centreon/ui';

import { selectedVisualizationAtom } from '../actionsAtoms';
import { Visualization } from '../../models';

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

  const [visualization, setVisualization] = useAtom(selectedVisualizationAtom);

  const onClick = (): void => {
    setVisualization(type);
  };

  return (
    <IconButton
      ariaLabel={t(title)}
      //   className="className"
      data-testid={title}
      title={t(title)}
      onClick={onClick}
    >
      <Image
        alt={title}
        imagePath={equals(visualization, type) ? IconOnActive : IconOnInactive}
      />
    </IconButton>
  );
};

export default Action;
