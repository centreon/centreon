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

import { useCopyToClipboard } from '@centreon/ui';

import { lazy, Suspense, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../Details/models';
import { SummarizeIncidentResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAiTimelineSummary,
  labelAiTimelineSummaryDisclaimer,
  labelClose,
  labelCopy,
  labelGeneratedBy,
  labelOnHost,
  labelSummarize,
  labelSummaryCopied,
  labelSummaryCopyFailed
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useSummarizeIncident from './useSummarizeIncident';

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
}

const SummarizeIncident = ({ details }: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<SummarizeIncidentResult | null>(null);

  const { summarize, isLoading } = useSummarizeIncident();
  const { copy } = useCopyToClipboard({
    errorMessage: t(labelSummaryCopyFailed),
    successMessage: t(labelSummaryCopied)
  });
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  const openSummary = (): void => {
    setResult(null);
    setIsDialogOpen(true);

    summarize(details).then((summary) => {
      if (summary === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(summary);
    });
  };

  return (
    <>
      <Tooltip title={isBudgetExhausted ? t(labelAiBudgetExhausted) : ''}>
        <span>
          <Button
            data-testid={labelSummarize}
            disabled={isBudgetExhausted}
            onClick={openSummary}
            size="small"
            startIcon={<AutoAwesomeIcon />}
          >
            {t(labelSummarize)}
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
          {t(labelAiTimelineSummary)} — {details.name}
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
          {isLoading || result === null ? (
            <div className={classes.loading}>
              <CircularProgress data-testid="summary-loading" />
            </div>
          ) : (
            <>
              <Suspense
                fallback={
                  <div className={classes.loading}>
                    <CircularProgress data-testid="summary-loading" />
                  </div>
                }
              >
                <MarkdownContent content={result.summary} />
              </Suspense>
              <Typography variant="caption">
                {t(labelGeneratedBy)} {result.model}
              </Typography>
            </>
          )}
        </DialogContent>
        <DialogActions>
          <Typography className={classes.disclaimer} variant="caption">
            {t(labelAiTimelineSummaryDisclaimer)}
          </Typography>
          <Button
            data-testid={labelCopy}
            disabled={result === null}
            onClick={() => result && copy(result.summary)}
            size="small"
          >
            {t(labelCopy)}
          </Button>
          <Button onClick={() => setIsDialogOpen(false)} size="small">
            {t(labelClose)}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default SummarizeIncident;
