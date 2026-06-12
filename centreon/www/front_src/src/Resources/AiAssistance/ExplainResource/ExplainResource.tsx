import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography
} from '@mui/material';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../Details/models';
import { ExplainResourceResult } from '../models';
import {
  labelAiExplanation,
  labelAiExplanationDisclaimer,
  labelClose,
  labelExplain,
  labelGeneratedBy
} from '../translatedLabels';
import useExplainResource from './useExplainResource';

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
  explanation: {
    whiteSpace: 'pre-wrap'
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

const ExplainResource = ({ details }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [result, setResult] = useState<ExplainResourceResult | null>(null);

  const { explain, isMutating } = useExplainResource();

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
      <Button
        data-testid={labelExplain}
        onClick={openExplanation}
        size="small"
        startIcon={<AutoAwesomeIcon />}
      >
        {t(labelExplain)}
      </Button>
      <Dialog
        fullWidth
        maxWidth="sm"
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
      >
        <DialogTitle>
          {t(labelAiExplanation)} — {details.name}
        </DialogTitle>
        <DialogContent>
          {isMutating || result === null ? (
            <div className={classes.loading}>
              <CircularProgress data-testid="explanation-loading" />
            </div>
          ) : (
            <>
              <Typography className={classes.explanation} variant="body2">
                {result.explanation}
              </Typography>
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
