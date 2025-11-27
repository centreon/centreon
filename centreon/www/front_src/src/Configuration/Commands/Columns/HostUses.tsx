import { ComponentColumnProps } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelHostHostTemplate } from '../translatedLabels';

const HostUses = ({
  row,
  isHovered,
  renderEllipsisTypography
}: ComponentColumnProps): ReactElement => {
  const { t } = useTranslation();

  const { hostsCount, hostTemplatesCount } = row;

  const name = renderEllipsisTypography?.({
    className: isHovered
      ? 'pl-2 text-text-primary'
      : 'pl-2 text-text-secondary',
    formattedString: `${hostsCount} (${hostTemplatesCount})`
  });

  return (
    <Tooltip label={t(labelHostHostTemplate)}>
      <div>{name}</div>
    </Tooltip>
  );
};

export default HostUses;
