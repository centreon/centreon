import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import { Button, Tooltip } from '@mui/material';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import AiResultDialog from '../AiResultDialog';
import { AiResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAiGraphExplanationDisclaimer,
  labelAiGraphExplanationOf,
  labelExplainGraph
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import { useLatestRequest } from '../useLatestRequest';
import { unescaped } from '../utils';
import useExplainGraph, { type GraphMetricSummary } from './useExplainGraph';

interface Props {
  details: ResourceDetails;
  metrics: Array<GraphMetricSummary>;
  timeRange?: string | null;
}

const ExplainGraph = ({
  details,
  metrics,
  timeRange = null
}: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<AiResult | null>(null);

  const { explain } = useExplainGraph();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();
  const startRequest = useLatestRequest();

  if (!isConfigured) {
    return null;
  }

  const hasMetrics = metrics.length > 0;
  const disabled = isBudgetExhausted || !hasMetrics;

  const openExplanation = (): void => {
    const isLatest = startRequest();

    setResult(null);
    setIsDialogOpen(true);

    explain({ details, metrics, timeRange }).then((explanation) => {
      if (!isLatest()) {
        return;
      }

      if (explanation === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult({ content: explanation.explanation, model: explanation.model });
    });
  };

  return (
    <>
      <Tooltip title={isBudgetExhausted ? t(labelAiBudgetExhausted) : ''}>
        <span>
          <Button
            color="primary"
            data-testid={labelExplainGraph}
            disabled={disabled}
            onClick={openExplanation}
            size="small"
            startIcon={<AutoAwesomeIcon />}
            variant="contained"
          >
            {t(labelExplainGraph)}
          </Button>
        </span>
      </Tooltip>
      <AiResultDialog
        disclaimer={t(labelAiGraphExplanationDisclaimer)}
        host={details.parent?.name}
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
        result={result}
        title={t(labelAiGraphExplanationOf, {
          name: details.name,
          ...unescaped
        })}
      />
    </>
  );
};

export default ExplainGraph;
