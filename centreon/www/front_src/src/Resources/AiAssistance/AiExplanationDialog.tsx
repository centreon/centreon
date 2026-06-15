import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography
} from '@mui/material';

import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Resource } from '../models';
import {
  labelAiAnalysis,
  labelAiAnalysisDisclaimer,
  labelAiExplanation,
  labelAiExplanationDisclaimer,
  labelClose,
  labelGeneratedBy,
  labelOnHost
} from './translatedLabels';

const MarkdownContent = lazy(() =>
  import('./MarkdownContent').then((module) => ({
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

interface Result {
  content: string;
  model: string;
}

interface Props {
  // the single resource being explained; null when correlating two or more
  explainedResource: Resource | null;
  isLoading: boolean;
  onClose: () => void;
  open: boolean;
  result: Result | null;
}

const AiExplanationDialog = ({
  explainedResource,
  isLoading,
  onClose,
  open,
  result
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Dialog fullWidth maxWidth="sm" onClose={onClose} open={open}>
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
        {isLoading || result === null ? (
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
        <Button onClick={onClose} size="small">
          {t(labelClose)}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default AiExplanationDialog;
