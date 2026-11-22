import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import { Button, Tooltip } from '@mui/material';

import { useCopyToClipboard } from '@centreon/ui';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import AiResultDialog from '../AiResultDialog';
import { AiResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAiTimelineSummaryDisclaimer,
  labelAiTimelineSummaryOf,
  labelCopy,
  labelNoRecentEvents,
  labelSummarize,
  labelSummaryCopied,
  labelSummaryCopyFailed
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import { useLatestRequest } from '../useLatestRequest';
import { unescaped } from '../utils';
import useHasRecentEvents from './useHasRecentEvents';
import useSummarizeIncident from './useSummarizeIncident';

interface Props {
  details: ResourceDetails;
}

const SummarizeIncident = ({ details }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<AiResult | null>(null);

  const { summarize } = useSummarizeIncident();
  const { copy } = useCopyToClipboard({
    errorMessage: t(labelSummaryCopyFailed),
    successMessage: t(labelSummaryCopied)
  });
  const { isConfigured, isBudgetExhausted } = useAiAvailability();
  const hasRecentEvents = useHasRecentEvents({
    enabled: isConfigured,
    timelineEndpoint: details.links?.endpoints?.timeline
  });
  const startRequest = useLatestRequest();

  if (!isConfigured) {
    return null;
  }

  const hasNoRecentEvents = hasRecentEvents === false;
  const disabled = isBudgetExhausted || hasNoRecentEvents;

  const getTooltip = (): string => {
    if (isBudgetExhausted) {
      return labelAiBudgetExhausted;
    }
    if (hasNoRecentEvents) {
      return labelNoRecentEvents;
    }

    return '';
  };

  const openSummary = (): void => {
    const isLatest = startRequest();

    setResult(null);
    setIsDialogOpen(true);

    summarize(details).then((summary) => {
      if (!isLatest()) {
        return;
      }

      if (summary === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult({ content: summary.summary, model: summary.model });
    });
  };

  return (
    <>
      <Tooltip title={t(getTooltip())}>
        {/* pushed to the right of the timeline action row */}
        <span className="ml-auto">
          <Button
            color="primary"
            data-testid={labelSummarize}
            disabled={disabled}
            onClick={openSummary}
            size="small"
            startIcon={<AutoAwesomeIcon />}
            variant="contained"
          >
            {t(labelSummarize)}
          </Button>
        </span>
      </Tooltip>
      <AiResultDialog
        actions={
          <Button
            data-testid={labelCopy}
            disabled={result === null}
            onClick={() => result && copy(result.content)}
            size="small"
          >
            {t(labelCopy)}
          </Button>
        }
        disclaimer={t(labelAiTimelineSummaryDisclaimer)}
        host={details.parent?.name}
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
        result={result}
        title={t(labelAiTimelineSummaryOf, {
          name: details.name,
          ...unescaped
        })}
      />
    </>
  );
};

export default SummarizeIncident;
