import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography
} from '@mui/material';

import { lazy, ReactNode, Suspense } from 'react';
import { useTranslation } from 'react-i18next';

import { AiResult } from './models';
import {
  labelAnswerTruncated,
  labelClose,
  labelGeneratedBy,
  labelOnHost
} from './translatedLabels';
import { unescaped } from './utils';

const MarkdownContent = lazy(() =>
  import('./MarkdownContent').then((module) => ({
    default: module.MarkdownContent
  }))
);

export const aiResultLoadingTestId = 'ai-result-loading';

interface Props {
  // more buttons, displayed before Close
  actions?: ReactNode;
  disclaimer: string;
  // the host of a service, displayed next to the title
  host?: string | null;
  onClose: () => void;
  open: boolean;
  // null while the answer is loading
  result: AiResult | null;
  title: string;
}

// The dialog every AI assistance feature shows its answer in.
const AiResultDialog = ({
  actions,
  disclaimer,
  host,
  onClose,
  open,
  result,
  title
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const loader = (
    <div className="flex justify-center p-6">
      <CircularProgress data-testid={aiResultLoadingTestId} />
    </div>
  );

  return (
    <Dialog fullWidth maxWidth="sm" onClose={onClose} open={open}>
      <DialogTitle>
        {title}
        {host && (
          <Typography
            className="ml-2"
            color="textSecondary"
            component="span"
            variant="subtitle1"
          >
            {t(labelOnHost, { host, ...unescaped })}
          </Typography>
        )}
      </DialogTitle>
      <DialogContent>
        {result === null ? (
          loader
        ) : (
          <>
            <Suspense fallback={loader}>
              <MarkdownContent content={result.content} />
            </Suspense>
            <Typography variant="caption">
              {t(labelGeneratedBy, { model: result.model, ...unescaped })}
            </Typography>
          </>
        )}
      </DialogContent>
      <DialogActions>
        <div className="flex grow flex-col pl-2">
          {result?.truncated && (
            <Typography className="text-text-primary" variant="caption">
              {t(labelAnswerTruncated)}
            </Typography>
          )}
          <Typography className="text-text-secondary" variant="caption">
            {disclaimer}
          </Typography>
        </div>
        {actions}
        <Button onClick={onClose} size="small">
          {t(labelClose)}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default AiResultDialog;
