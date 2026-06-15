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

import { IconButton, useSnackbar } from '@centreon/ui';

import { atom, useAtomValue, useSetAtom } from 'jotai';
import { KeyboardEvent, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../Details/models';
import {
  applyCurrentFilterDerivedAtom,
  searchAtom
} from '../../Filter/filterAtoms';
import { listingAtom } from '../../Listing/listingAtoms';
import { Resource, ResourceListing } from '../../models';
import AiExplanationDialog from '../AiExplanationDialog';
import useAnalyzeResources from '../AnalyzeResources/useAnalyzeResources';
import useExplainResource from '../ExplainResource/useExplainResource';
import {
  labelAiBudgetExhausted,
  labelAiCouldNotTranslate,
  labelAskAi,
  labelAskAiApply,
  labelAskAiDisclaimer,
  labelAskAiPlaceholder,
  labelNoResourcesToExplain
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import useAskAiFilter from './useAskAiFilter';

// the alert-correlation endpoint accepts at most this many resources
const maxResourcesToExplain = 50;

interface AnalysisResult {
  content: string;
  model: string;
}

// the explanation dialog state lives in the store, not in component state, so
// it survives any re-render or remount of AskAiFilter while the (async) request
// is in flight
const explainDialogOpenAtom = atom(false);
const explainResultAtom = atom<AnalysisResult | null>(null);
const explainedResourceAtom = atom<Resource | null>(null);
const explainLoadingAtom = atom(false);

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
  const { showWarningMessage } = useSnackbar();

  const [isOpen, setIsOpen] = useState(false);
  const [prompt, setPrompt] = useState('');
  const anchorRef = useRef<HTMLDivElement | null>(null);
  // an explain request waiting for the listing to refresh with the derived
  // filter; we capture the listing at request time and fire once it changes to
  // the new settled result
  const pendingExplainRef = useRef(false);
  const listingBaselineRef = useRef<ResourceListing | undefined>(undefined);

  const isDialogOpen = useAtomValue(explainDialogOpenAtom);
  const setIsDialogOpen = useSetAtom(explainDialogOpenAtom);
  const result = useAtomValue(explainResultAtom);
  const setResult = useSetAtom(explainResultAtom);
  const explainedResource = useAtomValue(explainedResourceAtom);
  const setExplainedResource = useSetAtom(explainedResourceAtom);
  const isExplainLoading = useAtomValue(explainLoadingAtom);
  const setExplainLoading = useSetAtom(explainLoadingAtom);

  const { isMutating, translate } = useAskAiFilter();
  const { explain } = useExplainResource();
  const { analyze } = useAnalyzeResources();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();

  const search = useAtomValue(searchAtom);
  const listing = useAtomValue(listingAtom);
  const setSearch = useSetAtom(searchAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);

  const runExplain = (resources: Array<Resource>): void => {
    const capped = resources.slice(0, maxResourcesToExplain);

    if (capped.length === 0) {
      showWarningMessage(t(labelNoResourcesToExplain));

      return;
    }

    const isSingle = capped.length === 1;
    setResult(null);
    setExplainedResource(isSingle ? capped[0] : null);
    setExplainLoading(true);
    setIsDialogOpen(true);

    const request = isSingle
      ? explain(capped[0] as unknown as ResourceDetails).then((explanation) =>
          explanation === null
            ? null
            : { content: explanation.explanation, model: explanation.model }
        )
      : analyze(capped).then((analysis) =>
          analysis === null
            ? null
            : { content: analysis.analysis, model: analysis.model }
        );

    request.then((analysisResult) => {
      setExplainLoading(false);

      if (analysisResult === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(analysisResult);
    });
  };

  // once the listing has refreshed with the derived filter (it changes from the
  // value captured when the request was made), explain what is now displayed
  useEffect(() => {
    if (!pendingExplainRef.current || listing === listingBaselineRef.current) {
      return;
    }

    pendingExplainRef.current = false;
    runExplain(listing?.result ?? []);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [listing]);

  if (!isConfigured) {
    return null;
  }

  const close = (): void => {
    setIsOpen(false);
    setPrompt('');
  };

  const apply = (): void => {
    const trimmedPrompt = prompt.trim();

    if (!trimmedPrompt || isMutating) {
      return;
    }

    // close the popup right away; progress is shown on the ask-AI icon while
    // the request is in flight (and, for explanations, in the dialog), so the
    // popup never lingers with an in-place spinner
    close();

    translate(trimmedPrompt, search).then((translation) => {
      if (translation === null) {
        showWarningMessage(t(labelAiCouldNotTranslate));

        return;
      }

      if (translation.intent === 'explain') {
        // The model's `scope` is unreliable (it sometimes returns a query while
        // still labelling the scope as "current"). Route on the concrete
        // signal instead: when it derived a query that differs from the current
        // filter, narrow the listing to that set, then explain it once the
        // listing refreshes. Otherwise explain whatever is currently displayed.
        if (translation.query && translation.query !== search) {
          listingBaselineRef.current = listing;
          pendingExplainRef.current = true;
          setSearch(translation.query);
          applyCurrentFilter();

          return;
        }

        runExplain(listing?.result ?? []);

        return;
      }

      // filter intent
      if (!translation.query) {
        showWarningMessage(t(labelAiCouldNotTranslate));

        return;
      }

      setSearch(translation.query);
      applyCurrentFilter();
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
        disabled={isBudgetExhausted || isMutating}
        onClick={() => setIsOpen((open) => !open)}
        size="small"
        title={isBudgetExhausted ? t(labelAiBudgetExhausted) : t(labelAskAi)}
      >
        {isMutating ? (
          <CircularProgress size={16} />
        ) : (
          <AutoAwesomeIcon
            color={isBudgetExhausted ? 'disabled' : 'primary'}
            fontSize="small"
          />
        )}
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
              multiline
              inputProps={{ 'data-testid': labelAskAiPlaceholder }}
              maxRows={4}
              onChange={(event) => setPrompt(event.target.value)}
              onKeyDown={onKeyDown}
              placeholder={t(labelAskAiPlaceholder)}
              size="small"
              value={prompt}
            />
            <Typography className={classes.disclaimer} variant="caption">
              {t(labelAskAiDisclaimer)}
            </Typography>
            <div className={classes.actions}>
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
      <AiExplanationDialog
        explainedResource={explainedResource}
        isLoading={isExplainLoading}
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
        result={result}
      />
    </div>
  );
};

export default AskAiFilter;
