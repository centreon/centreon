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

import { Resource } from '../../models';
import { CorrelateAlertsResult } from '../models';
import {
  labelAiAnalysis,
  labelAiAnalysisDisclaimer,
  labelAiBudgetExhausted,
  labelAnalyze,
  labelAnalyzeHint,
  labelAnalyzeMinSelection,
  labelClose,
  labelGeneratedBy
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

// correlation needs at least two resources to look for a common cause
const minResources = 2;

const AnalyzeResources = ({
  resources,
  displayCondensed = false
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<CorrelateAlertsResult | null>(null);

  const { analyze, isMutating } = useAnalyzeResources();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  const hasEnough = resources.length >= minResources;
  const disabled = isBudgetExhausted || !hasEnough;

  const getTooltip = (): string => {
    if (isBudgetExhausted) {
      return labelAiBudgetExhausted;
    }
    if (!hasEnough) {
      return labelAnalyzeMinSelection;
    }

    return labelAnalyzeHint;
  };

  const openAnalysis = (): void => {
    setResult(null);
    setIsDialogOpen(true);

    analyze(resources).then((analysis) => {
      if (analysis === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(analysis);
    });
  };

  return (
    <>
      {displayCondensed ? (
        <IconButton
          ariaLabel={t(labelAnalyze)}
          data-testid={labelAnalyze}
          disabled={disabled}
          onClick={openAnalysis}
          size="large"
          title={t(getTooltip())}
        >
          <AutoAwesomeIcon />
        </IconButton>
      ) : (
        <Tooltip title={t(getTooltip())}>
          <span>
            <Button
              color="primary"
              data-testid={labelAnalyze}
              disabled={disabled}
              onClick={openAnalysis}
              size="small"
              startIcon={<AutoAwesomeIcon />}
              variant="contained"
            >
              {t(labelAnalyze)}
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
        <DialogTitle>{t(labelAiAnalysis)}</DialogTitle>
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
                <MarkdownContent content={result.analysis} />
              </Suspense>
              <Typography variant="caption">
                {t(labelGeneratedBy)} {result.model}
              </Typography>
            </>
          )}
        </DialogContent>
        <DialogActions>
          <Typography className={classes.disclaimer} variant="caption">
            {t(labelAiAnalysisDisclaimer)}
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
