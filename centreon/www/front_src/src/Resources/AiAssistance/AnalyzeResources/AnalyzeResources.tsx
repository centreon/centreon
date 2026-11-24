import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import { Button, Tooltip } from '@mui/material';

import { IconButton } from '@centreon/ui';

import clsx from 'clsx';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Resource } from '../../models';
import { AiResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAnalyze,
  labelAnalyzeHint,
  labelAnalyzeMinSelection,
  labelAnalyzeMostSevere,
  labelExplain
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import { useLatestRequest } from '../useLatestRequest';
import { maxCorrelatedResources } from '../utils';
import AnalysisDialog from './AnalysisDialog';
import useAnalyzeOrExplain from './useAnalyzeOrExplain';

const labelSlotClassName = 'col-start-1 row-start-1 whitespace-nowrap';

interface Props {
  displayCondensed?: boolean;
  resources: Array<Resource>;
}

const AnalyzeResources = ({
  resources,
  displayCondensed = false
}: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<AiResult | null>(null);
  const [explainedResource, setExplainedResource] = useState<Resource | null>(
    null
  );

  const analyzeOrExplain = useAnalyzeOrExplain();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();
  const startRequest = useLatestRequest();

  if (!isConfigured) {
    return null;
  }

  const isSingle = resources.length === 1;
  const hasEnough = resources.length >= 1;
  const disabled = isBudgetExhausted || !hasEnough;
  const actionLabel = isSingle ? labelExplain : labelAnalyze;

  const getTooltip = (): string => {
    if (isBudgetExhausted) {
      return t(labelAiBudgetExhausted);
    }
    if (!hasEnough) {
      return t(labelAnalyzeMinSelection);
    }
    if (isSingle) {
      return '';
    }
    if (resources.length > maxCorrelatedResources) {
      return t(labelAnalyzeMostSevere, { max: maxCorrelatedResources });
    }

    return t(labelAnalyzeHint);
  };

  // the icon has no visible label, so always surface the action (or the reason
  // it is disabled) as its tooltip, like the other condensed toolbar buttons
  const getCondensedTitle = (): string => {
    if (isBudgetExhausted) {
      return labelAiBudgetExhausted;
    }
    if (!hasEnough) {
      return labelAnalyzeMinSelection;
    }

    return actionLabel;
  };

  const openAnalysis = (): void => {
    const isLatest = startRequest();

    setResult(null);
    setExplainedResource(isSingle ? resources[0] : null);
    setIsDialogOpen(true);

    analyzeOrExplain(resources).then((analysisResult) => {
      if (!isLatest()) {
        return;
      }

      if (analysisResult === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(analysisResult);
    });
  };

  return (
    <>
      {displayCondensed ? (
        <IconButton
          ariaLabel={t(actionLabel)}
          data-testid={actionLabel}
          disabled={disabled}
          onClick={openAnalysis}
          size="large"
          title={t(getCondensedTitle())}
        >
          <AutoAwesomeIcon />
        </IconButton>
      ) : (
        <Tooltip title={getTooltip()}>
          <span>
            <Button
              aria-label={t(actionLabel)}
              color="primary"
              data-testid={actionLabel}
              disabled={disabled}
              onClick={openAnalysis}
              size="small"
              startIcon={<AutoAwesomeIcon />}
              variant="contained"
            >
              {/* the two labels share one grid cell so the button always
                  reserves the width of the wider one and does not resize
                  when switching between them */}
              <span className="grid justify-items-center">
                <span
                  className={clsx(labelSlotClassName, { invisible: !isSingle })}
                >
                  {t(labelExplain)}
                </span>
                <span
                  className={clsx(labelSlotClassName, { invisible: isSingle })}
                >
                  {t(labelAnalyze)}
                </span>
              </span>
            </Button>
          </span>
        </Tooltip>
      )}
      <AnalysisDialog
        explainedResource={explainedResource}
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
        result={result}
      />
    </>
  );
};

export default AnalyzeResources;
