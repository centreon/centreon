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

import { useAtom, useAtomValue, useSetAtom, useStore } from 'jotai';
import { KeyboardEvent, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  appliedFilterAtom,
  applyCurrentFilterDerivedAtom,
  searchAtom
} from '../../Filter/filterAtoms';
import { Filter } from '../../Filter/models';
import { listingAtom, sendingAtom } from '../../Listing/listingAtoms';
import { Resource, ResourceListing } from '../../models';
import AnalysisDialog from '../AnalyzeResources/AnalysisDialog';
import useAnalyzeOrExplain from '../AnalyzeResources/useAnalyzeOrExplain';
import {
  askAiDialogOpenAtom,
  askAiExplainedResourceAtom,
  askAiRequestIdAtom,
  askAiResultAtom
} from '../atoms';
import {
  labelAiBudgetExhausted,
  labelAiCouldNotTranslate,
  labelAskAi,
  labelAskAiApply,
  labelAskAiDisclaimer,
  labelAskAiPlaceholder,
  labelAskAiUnsupported,
  labelNoResourcesToExplain
} from '../translatedLabels';
import useAiAvailability from '../useAiAvailability';
import { useLatestRequest } from '../useLatestRequest';
import useAskAiFilter from './useAskAiFilter';

// An explanation waiting for the listing to load the filter derived from the
// question.
interface PendingExplain {
  // the listing displayed when the filter was applied
  baseline: ResourceListing | undefined;
  // the filter applied for the question: another one means the user moved on
  filter: Filter;
  // a load of the previous filter was still in flight: what it lands is not
  // the derived filter's
  hasStaleLoad: boolean;
  // the load of the derived filter has started
  hasStarted: boolean;
}

const AskAiFilter = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();
  const store = useStore();

  const [isOpen, setIsOpen] = useState(false);
  const [prompt, setPrompt] = useState('');
  const anchorRef = useRef<HTMLDivElement | null>(null);
  const pendingExplainRef = useRef<PendingExplain | null>(null);

  const [isDialogOpen, setIsDialogOpen] = useAtom(askAiDialogOpenAtom);
  const [result, setResult] = useAtom(askAiResultAtom);
  const [explainedResource, setExplainedResource] = useAtom(
    askAiExplainedResourceAtom
  );

  const { isMutating, translate } = useAskAiFilter();
  const analyzeOrExplain = useAnalyzeOrExplain();
  const { isConfigured, isBudgetExhausted } = useAiAvailability();
  const startRequest = useLatestRequest(askAiRequestIdAtom);

  const search = useAtomValue(searchAtom);
  const listing = useAtomValue(listingAtom);
  const isListingLoading = useAtomValue(sendingAtom);
  const appliedFilter = useAtomValue(appliedFilterAtom);
  const setSearch = useSetAtom(searchAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);

  const runExplain = (resources: Array<Resource>): void => {
    if (resources.length === 0) {
      showWarningMessage(t(labelNoResourcesToExplain));

      return;
    }

    const isLatest = startRequest();

    setResult(null);
    setExplainedResource(resources.length === 1 ? resources[0] : null);
    setIsDialogOpen(true);

    analyzeOrExplain(resources).then((analysisResult) => {
      if (!isLatest()) {
        return;
      }

      if (analysisResult === null) {
        setIsDialogOpen(false);

        return;
      }

      setResult(analysisResult);
    });
  };

  // Explains the first page of the derived filter once it has loaded. Other
  // listings may land meanwhile (another page, the previous filter); a load
  // that settles without a new listing has failed, which drops the request.
  useEffect(() => {
    const pending = pendingExplainRef.current;

    if (!pending) {
      return;
    }

    if (appliedFilter !== pending.filter) {
      pendingExplainRef.current = null;

      return;
    }

    if (isListingLoading) {
      pending.hasStarted = true;

      return;
    }

    if (!pending.hasStarted) {
      return;
    }

    if (listing === pending.baseline) {
      pendingExplainRef.current = null;

      return;
    }

    if (pending.hasStaleLoad) {
      pending.hasStaleLoad = false;
      pending.baseline = listing;

      return;
    }

    if (listing?.meta?.page !== 1) {
      return;
    }

    pendingExplainRef.current = null;
    runExplain(listing.result);
    // runExplain is not a dependency: it changes on every render, and the one
    // of the render that changed the listing is up to date
  }, [listing, isListingLoading, appliedFilter]);

  if (!isConfigured) {
    return null;
  }

  const close = (): void => {
    setIsOpen(false);
    setPrompt('');
  };

  const explainDerivedFilter = (query: string): void => {
    setSearch(query);
    applyCurrentFilter();

    pendingExplainRef.current = {
      baseline: store.get(listingAtom),
      filter: store.get(appliedFilterAtom),
      hasStaleLoad: store.get(sendingAtom),
      hasStarted: store.get(sendingAtom)
    };
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
      // the error snackbar already says why the call failed
      if (translation === null) {
        return;
      }

      if (translation.intent === 'unsupported') {
        showWarningMessage(t(labelAskAiUnsupported));

        return;
      }

      if (translation.intent === 'explain') {
        // The model's `scope` is unreliable (it sometimes returns a query while
        // still labelling the scope as "current"). Route on the concrete
        // signal instead: when it derived a query that differs from the current
        // filter, narrow the listing to that set, then explain it once the
        // listing refreshes. Otherwise explain whatever is currently displayed.
        if (translation.query && translation.query !== search) {
          explainDerivedFilter(translation.query);

          return;
        }

        runExplain(store.get(listingAtom)?.result ?? []);

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
        open={isOpen}
        placement="bottom-end"
        // above the tooltips: Tailwind has no token for the theme's z-index
        sx={{ zIndex: 'tooltip' }}
      >
        <ClickAwayListener onClickAway={() => setIsOpen(false)}>
          <Paper className="flex w-100 flex-col gap-2 p-3">
            <TextField
              autoFocus
              fullWidth
              inputProps={{ 'data-testid': labelAskAiPlaceholder }}
              maxRows={4}
              multiline
              onChange={(event) => setPrompt(event.target.value)}
              onKeyDown={onKeyDown}
              placeholder={t(labelAskAiPlaceholder)}
              size="small"
              value={prompt}
            />
            <Typography className="text-text-secondary" variant="caption">
              {t(labelAskAiDisclaimer)}
            </Typography>
            <div className="flex items-center justify-end gap-2">
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
      <AnalysisDialog
        explainedResource={explainedResource}
        onClose={() => setIsDialogOpen(false)}
        open={isDialogOpen}
        result={result}
      />
    </div>
  );
};

export default AskAiFilter;
