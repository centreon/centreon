import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import {
  Button,
  CircularProgress,
  ClickAwayListener,
  Paper,
  Popper,
  TextField,
  Typography
} from '@mui/material';

import { IconButton } from '@centreon/ui';

import { KeyboardEvent, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelAiBudgetExhausted,
  labelAskAi,
  labelAskAiApply,
  labelAskAiDisclaimer,
  labelAskAiPlaceholder
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useAskAiFilter from './useAskAiFilter';

const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1),
    justifyContent: 'flex-end'
  },
  disclaimer: {
    color: theme.palette.text.secondary
  },
  paper: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    padding: theme.spacing(1.5),
    width: theme.spacing(50)
  },
  popper: {
    zIndex: theme.zIndex.tooltip
  }
}));

const AskAiFilter = (): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const [prompt, setPrompt] = useState('');
  const anchorRef = useRef<HTMLDivElement | null>(null);

  const { isMutating, translate } = useAskAiFilter();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  if (!isConfigured) {
    return null;
  }

  const apply = (): void => {
    if (!prompt.trim() || isMutating) {
      return;
    }

    translate(prompt.trim()).then((succeeded) => {
      if (succeeded) {
        setIsOpen(false);
        setPrompt('');
      }
    });
  };

  const onKeyDown = (event: KeyboardEvent): void => {
    if (event.key === 'Enter') {
      event.preventDefault();
      apply();
    }
  };

  return (
    <div ref={anchorRef}>
      <IconButton
        ariaLabel={t(labelAskAi)}
        data-testid={labelAskAi}
        disabled={isBudgetExhausted}
        onClick={() => setIsOpen((open) => !open)}
        size="small"
        title={isBudgetExhausted ? t(labelAiBudgetExhausted) : t(labelAskAi)}
      >
        <AutoAwesomeIcon
          color={isBudgetExhausted ? 'disabled' : 'primary'}
          fontSize="small"
        />
      </IconButton>
      <Popper
        anchorEl={anchorRef.current}
        className={classes.popper}
        open={isOpen}
        placement="bottom-end"
      >
        <ClickAwayListener onClickAway={() => setIsOpen(false)}>
          <Paper className={classes.paper}>
            <TextField
              autoFocus
              fullWidth
              inputProps={{ 'data-testid': labelAskAiPlaceholder }}
              onChange={(event) => setPrompt(event.target.value)}
              onKeyDown={onKeyDown}
              placeholder={t(labelAskAiPlaceholder)}
              size="small"
              value={prompt}
            />
            <div className={classes.actions}>
              <Typography className={classes.disclaimer} variant="caption">
                {t(labelAskAiDisclaimer)}
              </Typography>
              {isMutating && <CircularProgress size={20} />}
              <Button
                data-testid={labelAskAiApply}
                disabled={!prompt.trim() || isMutating}
                onClick={apply}
                size="small"
                variant="contained"
              >
                {t(labelAskAiApply)}
              </Button>
            </div>
          </Paper>
        </ClickAwayListener>
      </Popper>
    </div>
  );
};

export default AskAiFilter;
