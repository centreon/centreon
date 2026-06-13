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
import { ExplainResourceResult } from '../models';
import {
  labelAiBudgetExhausted,
  labelAiExplanation,
  labelAiExplanationDisclaimer,
  labelClose,
  labelExplain,
  labelGeneratedBy,
  labelOnHost
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useExplainResource from './useExplainResource';

const MarkdownContent = lazy(() =>
  import('../MarkdownContent').then((module) => ({
    default: module.MarkdownContent
  }))
);

const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    justifyContent: 'flex-end',
    padding: theme.spacing(0, 2)
  },
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

const ExplainResource = ({ details }: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<ExplainResourceResult | null>(null);

  const { explain, isMutating } = useExplainResource();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  const openExplanation = (): void => {
    setResult(null);
    setIsDialogOpen(true);

    explain(details).then((explanation) => {
      if (explanation === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(explanation);
    });
  };

  return (
    <div className={classes.container}>
      <Tooltip title={isBudgetExhausted ? t(labelAiBudgetExhausted) : ''}>
        <span>
          <Button
            data-testid={labelExplain}
            disabled={isBudgetExhausted}
            onClick={openExplanation}
            size="small"
            startIcon={<AutoAwesomeIcon />}
          >
            {t(labelExplain)}
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
          {t(labelAiExplanation)} — {details.name}
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
              <CircularProgress data-testid="explanation-loading" />
            </div>
          ) : (
            <>
              <Suspense
                fallback={
                  <div className={classes.loading}>
                    <CircularProgress data-testid="explanation-loading" />
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
            {t(labelAiExplanationDisclaimer)}
          </Typography>
          <Button onClick={() => setIsDialogOpen(false)} size="small">
            {t(labelClose)}
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
};

export default ExplainResource;
