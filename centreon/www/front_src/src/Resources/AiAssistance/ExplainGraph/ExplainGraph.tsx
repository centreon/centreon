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

import { lazy, Suspense, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../Details/models';
import { ExplainGraphResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAiGraphExplanation,
  labelAiGraphExplanationDisclaimer,
  labelClose,
  labelExplainGraph,
  labelGeneratedBy,
  labelOnHost
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useExplainGraph, { type GraphMetricSummary } from './useExplainGraph';

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
  details: ResourceDetails;
  metrics: Array<GraphMetricSummary>;
  timeRange?: string | null;
}

const ExplainGraph = ({
  details,
  metrics,
  timeRange = null
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<ExplainGraphResult | null>(null);

  const { explain, isMutating } = useExplainGraph();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  const hasMetrics = metrics.length > 0;
  const disabled = isBudgetExhausted || !hasMetrics;

  const openExplanation = (): void => {
    setResult(null);
    setIsDialogOpen(true);

    explain({ details, metrics, timeRange }).then((explanation) => {
      if (explanation === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(explanation);
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
      <Dialog
        fullWidth
        maxWidth="sm"
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
      >
        <DialogTitle>
          {t(labelAiGraphExplanation)} — {details.name}
          {details.parent?.name && (
            <Typography
              color="textSecondary"
              component="span"
              sx={{ ml: 1 }}
              variant="subtitle1"
            >
              {t(labelOnHost, { host: details.parent.name })}
            </Typography>
          )}
        </DialogTitle>
        <DialogContent>
          {isMutating || result === null ? (
            <div className={classes.loading}>
              <CircularProgress data-testid="graph-explanation-loading" />
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
                <MarkdownContent content={result.explanation} />
              </Suspense>
              <Typography variant="caption">
                {t(labelGeneratedBy)} {result.model}
              </Typography>
            </>
          )}
        </DialogContent>
        <DialogActions>
          <Typography className={classes.disclaimer} variant="caption">
            {t(labelAiGraphExplanationDisclaimer)}
          </Typography>
          <Button onClick={() => setIsDialogOpen(false)} size="small">
            {t(labelClose)}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default ExplainGraph;
