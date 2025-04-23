import { useTranslation } from 'react-i18next';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import AcknowledgeChip from '../../../../Chip/Acknowledge';
import { labelAcknowledgedBy, labelAt } from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';
import StateCard from '../StateCard';

interface Props {
  details: ResourceDetails;
}

const AcknowledgementCard = ({ details }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { toDateTime } = useLocaleDateTimeFormat();

  return (
    <StateCard
      chip={<AcknowledgeChip />}
      commentLine={details.acknowledgement?.comment || ''}
      contentLines={[
        {
          line: `${details.acknowledgement?.author_name} ${t(
            labelAt
          )} ${toDateTime(details.acknowledgement?.entry_time || '')}`,
          testId: 'acknowledged_by_at'
        }
      ]}
      title={t(labelAcknowledgedBy)}
    />
  );
};

export default AcknowledgementCard;
