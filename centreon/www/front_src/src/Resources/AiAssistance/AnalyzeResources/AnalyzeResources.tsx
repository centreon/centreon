import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Tooltip,
  Typography
} from '@mui/material';

import { IconButton } from '@centreon/ui';

import { lazy, Suspense, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../Details/models';
import { Resource } from '../../models';
import useExplainResource from '../ExplainResource/useExplainResource';
import {
  labelAiAnalysis,
  labelAiAnalysisDisclaimer,
  labelAiBudgetExhausted,
  labelAiExplanation,
  labelAiExplanationDisclaimer,
  labelAnalyze,
  labelAnalyzeHint,
  labelAnalyzeMinSelection,
  labelClose,
  labelExplain,
  labelGeneratedBy,
  labelOnHost
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useAnalyzeResources from './useAnalyzeResources';

const MarkdownContent = lazy(() =>
  import('../MarkdownContent').then((module) => ({
    default: module.MarkdownContent
  }))
);

const useStyles = makeStyles()((theme) => ({
  disclaimer: {
    color: theme.palette.text.secondary,
    flexGrow: 1,
    paddingLeft: theme.spacing(1)
  },
  // the two labels share one grid cell so the button always reserves the width
  // of the wider one and does not resize when switching between them
  labelHidden: {
    visibility: 'hidden'
  },
  labelSlot: {
    gridArea: '1 / 1',
    whiteSpace: 'nowrap'
  },
  labelStack: {
    display: 'grid',
    justifyItems: 'center'
  },
  loading: {
    display: 'flex',
    justifyContent: 'center',
    padding: theme.spacing(3)
  }
}));

interface Props {
  displayCondensed?: boolean;
  resources: Array<Resource>;
}

interface AnalysisResult {
  content: string;
  model: string;
}

const AnalyzeResources = ({
  resources,
  displayCondensed = false
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<AnalysisResult | null>(null);
  // the resource being explained drives the single-resource dialog; null while
  // correlating two or more resources
  const [explainedResource, setExplainedResource] = useState<Resource | null>(
    null
  );

  const { analyze, isMutating: isAnalyzing } = useAnalyzeResources();
  const { explain, isMutating: isExplaining } = useExplainResource();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  // a single resource is explained on its own; two or more are correlated to
  // surface a likely common cause
  const isSingle = resources.length === 1;
  const hasEnough = resources.length >= 1;
  const disabled = isBudgetExhausted || !hasEnough;
  const isMutating = isAnalyzing || isExplaining;
  // a single resource is explained, several are analyzed together: the button
  // label follows the action it triggers
  const actionLabel = isSingle ? labelExplain : labelAnalyze;

  const getTooltip = (): string => {
    if (isBudgetExhausted) {
      return labelAiBudgetExhausted;
    }
    if (!hasEnough) {
      return labelAnalyzeMinSelection;
    }
    if (isSingle) {
      return '';
    }

    return labelAnalyzeHint;
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
    setResult(null);
    setExplainedResource(isSingle ? resources[0] : null);
    setIsDialogOpen(true);

    const request = isSingle
      ? explain(resources[0] as unknown as ResourceDetails).then(
          (explanation) =>
            explanation === null
              ? null
              : { content: explanation.explanation, model: explanation.model }
        )
      : analyze(resources).then((analysis) =>
          analysis === null
            ? null
            : { content: analysis.analysis, model: analysis.model }
        );

    request.then((analysisResult) => {
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
        <Tooltip title={t(getTooltip())}>
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
              <span className={classes.labelStack}>
                <span
                  className={cx(classes.labelSlot, {
                    [classes.labelHidden]: !isSingle
                  })}
                >
                  {t(labelExplain)}
                </span>
                <span
                  className={cx(classes.labelSlot, {
                    [classes.labelHidden]: isSingle
                  })}
                >
                  {t(labelAnalyze)}
                </span>
              </span>
            </Button>
          </span>
        </Tooltip>
      )}
      <Dialog
        fullWidth
        maxWidth="sm"
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
      >
        <DialogTitle>
          {explainedResource ? (
            <>
              {t(labelAiExplanation)} — {explainedResource.name}
              {explainedResource.parent?.name && (
                <Typography
                  color="textSecondary"
                  component="span"
                  sx={{ ml: 1 }}
                  variant="subtitle1"
                >
                  {t(labelOnHost, { host: explainedResource.parent.name })}
                </Typography>
              )}
            </>
          ) : (
            t(labelAiAnalysis)
          )}
        </DialogTitle>
        <DialogContent>
          {isMutating || result === null ? (
            <div className={classes.loading}>
              <CircularProgress data-testid="analysis-loading" />
            </div>
          ) : (
            <>
              <Suspense
                fallback={
                  <div className={classes.loading}>
                    <CircularProgress />
                  </div>
                }
              >
                <MarkdownContent content={result.content} />
              </Suspense>
              <Typography variant="caption">
                {t(labelGeneratedBy)} {result.model}
              </Typography>
            </>
          )}
        </DialogContent>
        <DialogActions>
          <Typography className={classes.disclaimer} variant="caption">
            {t(
              explainedResource
                ? labelAiExplanationDisclaimer
                : labelAiAnalysisDisclaimer
            )}
          </Typography>
          <Button onClick={() => setIsDialogOpen(false)} size="small">
            {t(labelClose)}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default AnalyzeResources;
