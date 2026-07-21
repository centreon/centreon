import { ComponentColumnProps } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelServiceServiceemplate } from '../translatedLabels';

const ServicetUses = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): ReactElement => {
  const { t } = useTranslation();

  const { servicesCount, serviceTemplatesCount } = row;

  const name = renderEllipsisTypography?.({
    className: isHovered
      ? 'pl-2 text-text-primary'
      : 'pl-2 text-text-secondary',
    formattedString: `${servicesCount} (${serviceTemplatesCount})`
  });

  return (
    <Tooltip label={t(labelServiceServiceemplate)}>
      <div>{name}</div>
    </Tooltip>
  );
};

export default ServicetUses;
