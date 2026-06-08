// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import CloseIcon from '@mui/icons-material/Close';
import IconSearch from '@mui/icons-material/Search';
import TuneIcon from '@mui/icons-material/Tune';
import {
  Box,
  CircularProgress,
  ClickAwayListener,
  MenuItem,
  Paper,
  Popper
} from '@mui/material';

import {
  getData,
  IconButton,
  Filter as MemoizedFilter,
  SearchField,
  useRequest
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { userAtom } from '@centreon/ui-context';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import {
  concat,
  dec,
  difference,
  dropLast,
  equals,
  find,
  inc,
  isEmpty,
  isNil,
  last,
  length,
  map,
  not,
  or,
  pipe,
  pluck,
  remove,
  uniq
} from 'ramda';
import {
  type KeyboardEvent,
  lazy,
  type RefObject,
  Suspense,
  useEffect,
  useRef,
  useState
} from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelClearFilter,
  labelMoreFilters,
  labelSearch,
  labelSearchBar
} from '../translatedLabels';
import {
  type DynamicCriteriaParametersAndValues,
  getAutocompleteSuggestions,
  getDynamicCriteriaParametersAndValue,
  replaceMiddleSpace
} from './Criterias/searchQueryLanguage';
import { selectedStatusByResourceTypeAtom } from './criteriasNewInterface/basicFilter/atoms';
import FilterChips, { useActiveFilterChips } from './FilterChips';
import FilterViews from './FilterViews';
import {
  applyCurrentFilterDerivedAtom,
  clearFilterDerivedAtom,
  currentFilterAtom,
  isCriteriasPanelOpenAtom,
  searchAtom,
  setNewFilterDerivedAtom
} from './filterAtoms';
import SearchHelp from './SearchHelp';
import useBackToVisualizationByAll from './useBackToVisualizationByAll';
import useFilterByModule from './useFilterByModule';

export const renderEndAdornmentFilter = (onClear) => (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  return (
    <div className={classes.End}>
      <IconButton
        ariaLabel={t(labelClearFilter) as string}
        data-testid={labelClearFilter}
        onClick={onClear}
        size="small"
        title={t(labelClearFilter) as string}
      >
        <CloseIcon color="action" fontSize="small" />
      </IconButton>
    </div>
  );
};

interface DynamicCriteriaResult {
  result: Array<{ level: string; name: string }>;
}

const useStyles = makeStyles()((theme) => ({
  autocompletePopper: {
    zIndex: theme.zIndex.tooltip
  },
  chipsOverlay: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.paper,
    cursor: 'text',
    display: 'flex',
    gap: theme.spacing(0.75),
    inset: 0,
    overflow: 'hidden',
    paddingLeft: theme.spacing(1.75),
    position: 'absolute'
  },
  chipsOverlayExpanded: {
    alignItems: 'flex-start',
    inset: 'auto',
    overflow: 'visible',
    paddingBlock: theme.spacing(0.75),
    position: 'relative'
  },
  chipsOverlayIcon: {
    color: theme.palette.text.secondary,
    display: 'flex'
  },
  container: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1),
    gridTemplateColumns: '1fr',
    width: '100%'
  },
  End: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row'
  },
  hidden: {
    display: 'none'
  },
  loader: { display: 'flex', justifyContent: 'center' },
  searchbarContainer: {
    // The whole bar is the pill; the inner field has no border of its own.
    '& .MuiOutlinedInput-notchedOutline': {
      border: 'none'
    },
    alignItems: 'center',
    backgroundColor: theme.palette.background.paper,
    border: `1.5px solid ${theme.palette.divider}`,
    borderRadius: '20px',
    display: 'flex',
    gap: theme.spacing(0.5),
    overflow: 'hidden',
    paddingRight: theme.spacing(1.5),
    position: 'relative',
    width: '100%'
  },
  searchFieldWrap: {
    flex: 1,
    minWidth: 0,
    position: 'relative'
  },
  wrapper: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  }
}));

const Criterias = lazy(() => import('./Criterias'));

const debounceTimeInMs = 500;

const isDefined = pipe(isNil, not);

const Filter = (): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const { newSelectableCriterias } = useFilterByModule();

  const [chipsExpanded, setChipsExpanded] = useState(false);

  const [isSearchFieldFocus, setIsSearchFieldFocused] = useState(false);
  const [autocompleteAnchor, setAutocompleteAnchor] =
    useState<HTMLDivElement | null>(null);
  const searchRef = useRef<HTMLInputElement>();
  const [autoCompleteSuggestions, setAutoCompleteSuggestions] = useState<
    Array<string>
  >([]);
  const [cursorPosition, setCursorPosition] = useState(0);
  const [selectedSuggestionIndex, setSelectedSuggestionIndex] = useState(0);
  const dynamicSuggestionsDebounceRef = useRef<NodeJS.Timeout | null>(null);

  const {
    sendRequest: sendDynamicCriteriaValueRequests,
    sending: sendingDynamicCriteriaValueRequests
  } = useRequest<DynamicCriteriaResult>({
    request: getData
  });

  const [search, setSearch] = useAtom(searchAtom);
  const currentFilter = useAtomValue(currentFilterAtom);
  const user = useAtomValue(userAtom);
  const [isCriteriasPanelOpen, setIsCriteriasPanelOpen] = useAtom(
    isCriteriasPanelOpenAtom
  );
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const setNewFilter = useSetAtom(setNewFilterDerivedAtom);
  const clearFilter = useSetAtom(clearFilterDerivedAtom);
  const setSelectedStatusByResourceType = useSetAtom(
    selectedStatusByResourceTypeAtom
  );

  useBackToVisualizationByAll();

  const open = Boolean(autocompleteAnchor);

  const activeChips = useActiveFilterChips();
  const showChipsOverlay = !isSearchFieldFocus && activeChips.length > 0;

  const focusSearchField = (event): void => {
    if (event.target.closest('button')) {
      return;
    }
    event.preventDefault();
    setChipsExpanded(false);
    searchRef?.current?.focus();
  };

  const toggleChipsExpand = (): void => {
    setChipsExpanded((previous) => !previous);
  };

  const toggleCriteriasPanel = (): void => {
    setIsCriteriasPanelOpen((previous) => !previous);
  };

  const clearFilters = (): void => {
    clearFilter();
    setSelectedStatusByResourceType(null);
  };

  const clearDebounceDynamicSuggestions = (): void => {
    if (dynamicSuggestionsDebounceRef.current) {
      clearInterval(dynamicSuggestionsDebounceRef.current as NodeJS.Timeout);
    }
  };

  const loadDynamicCriteriaSuggestion = ({
    criteria,
    values
  }: DynamicCriteriaParametersAndValues): void => {
    const { buildAutocompleteEndpoint, autocompleteSearch, label } = criteria;

    const lastValue = last(values);

    const selectedValues = remove(-1, 1, values);

    sendDynamicCriteriaValueRequests({
      endpoint: buildAutocompleteEndpoint({
        limit: 5,
        page: 1,
        search: {
          conditions: [
            ...(autocompleteSearch?.conditions || []),
            not(isEmpty(selectedValues))
              ? {
                  field: 'name',
                  values: { $ni: selectedValues }
                }
              : {}
          ],
          regex: {
            fields: ['name'],
            value: lastValue || ''
          }
        }
      })
    }).then(({ result }): void => {
      const results = label.includes('severity level')
        ? pluck('level', result)
        : pluck('name', result);

      const formattedResult = uniq(results.map((item) => item.toString()));

      const lastValueEqualsToAResult = find(equals(lastValue), formattedResult);

      const notSelectedValues = difference(formattedResult, values);

      if (or(lastValueEqualsToAResult, isEmpty(formattedResult))) {
        const res = [
          ...notSelectedValues,
          ...map(concat(','), notSelectedValues)
        ];

        setAutoCompleteSuggestions(res);

        return;
      }

      setAutoCompleteSuggestions(formattedResult);
    });
  };

  const debounceDynamicSuggestions = (
    props: DynamicCriteriaParametersAndValues
  ): void => {
    clearDebounceDynamicSuggestions();

    dynamicSuggestionsDebounceRef.current = setTimeout((): void => {
      loadDynamicCriteriaSuggestion(props);
    }, debounceTimeInMs);
  };

  useEffect(() => {
    setSelectedSuggestionIndex(0);

    if (isEmpty(search.charAt(dec(cursorPosition)).trim())) {
      clearDebounceDynamicSuggestions();
      setAutoCompleteSuggestions([]);
      setAutocompleteAnchor(null);

      return;
    }

    const dynamicCriteriaParameters = getDynamicCriteriaParametersAndValue({
      cursorPosition,
      newSelectableCriterias,
      search
    });

    if (isDefined(dynamicCriteriaParameters) && isSearchFieldFocus) {
      debounceDynamicSuggestions(
        dynamicCriteriaParameters as DynamicCriteriaParametersAndValues
      );

      return;
    }

    clearDebounceDynamicSuggestions();
    setAutoCompleteSuggestions([]);

    setAutoCompleteSuggestions(
      getAutocompleteSuggestions({
        cursorPosition,
        newSelectableCriterias,
        search
      })
    );
  }, [search, cursorPosition]);

  const updateCursorPosition = (): void => {
    setCursorPosition(searchRef?.current?.selectionStart || 0);
  };

  useEffect(() => {
    updateCursorPosition();
  }, [searchRef?.current?.selectionStart]);

  useEffect(() => {
    const dynamicCriteriaParameters = getDynamicCriteriaParametersAndValue({
      cursorPosition,
      newSelectableCriterias,
      search
    });

    const isDynamicCriteria = isDefined(dynamicCriteriaParameters);

    if (isDynamicCriteria && isSearchFieldFocus) {
      setAutocompleteAnchor(searchRef?.current as HTMLDivElement);

      return;
    }

    if (isEmpty(autoCompleteSuggestions)) {
      setAutocompleteAnchor(null);

      return;
    }

    setAutocompleteAnchor(searchRef?.current as HTMLDivElement);
  }, [autoCompleteSuggestions]);

  const acceptAutocompleteSuggestionAtIndex = (index: number): void => {
    setNewFilter(t);

    const acceptedSuggestion = replaceMiddleSpace(
      autoCompleteSuggestions[index]
    );

    if (equals(search[cursorPosition], ',')) {
      setSearch(search + acceptedSuggestion);

      return;
    }

    const searchBeforeCursor = search.slice(0, cursorPosition + 1);
    // the search is composed of "expressions" separated by whitespaces
    // (like "status:OK" for instance)
    const expressionBeforeCursor =
      last(searchBeforeCursor.trim().split(' ')) || '';

    // an expression is "complete" when it has a value that is not in the middle of an input
    // ("status:"" or "status:OK", for instance, but not "status:O")
    const isExpressionComplete =
      expressionBeforeCursor.endsWith(':') ||
      expressionBeforeCursor.endsWith(',') ||
      acceptedSuggestion.startsWith(',');

    const expressionAfterSeparator = isExpressionComplete
      ? ''
      : last(expressionBeforeCursor.split(/:|,/)) || '';

    const completedWord = acceptedSuggestion.slice(
      expressionAfterSeparator.length,
      acceptedSuggestion.length
    );

    const cursorCompletionShift =
      acceptedSuggestion.length - expressionAfterSeparator.length;

    const isExpressionEmpty = expressionAfterSeparator === '';
    const searchCutPosition = isExpressionEmpty
      ? cursorPosition + 1
      : cursorPosition;

    const searchBeforeCompletedWord = search.slice(0, searchCutPosition);
    const searchAfterCompletedWord = search.slice(searchCutPosition);

    const searchBeforeSuggestion = isEmpty(expressionAfterSeparator.trim())
      ? searchBeforeCompletedWord.trim()
      : dropLast(
          expressionAfterSeparator.length,
          searchBeforeCompletedWord.trim()
        );

    const suggestion = isEmpty(expressionAfterSeparator.trim())
      ? completedWord
      : acceptedSuggestion;

    const searchWithAcceptedSuggestion = [
      searchBeforeSuggestion,
      suggestion,
      searchAfterCompletedWord.trim() === '' ? '' : ' ',
      searchAfterCompletedWord
    ].join('');

    setCursorPosition(cursorPosition + cursorCompletionShift);
    setAutoCompleteSuggestions([]);
    clearDebounceDynamicSuggestions();

    if (isNil(search[cursorPosition])) {
      setSearch(searchWithAcceptedSuggestion);

      return;
    }

    // when the autocompletion takes part somewhere that is not at the end of the output,
    // we need to shift the corresponding expression to the end, because that's where the cursor will end up
    const expressionToShiftToTheEnd = expressionBeforeCursor.includes(':')
      ? expressionBeforeCursor + completedWord
      : acceptedSuggestion;
    setSearch(
      [
        searchWithAcceptedSuggestion
          .replace(expressionToShiftToTheEnd, '')
          .trim(),
        ' ',
        expressionToShiftToTheEnd
      ].join('')
    );
  };

  const inputKey = (event: KeyboardEvent): void => {
    const enterKeyPressed = event.key === 'Enter';
    const tabKeyPressed = event.key === 'Tab';
    const escapeKeyPressed = event.key === 'Escape';
    const arrowDownKeyPressed = event.key === 'ArrowDown';
    const arrowUpKeyPressed = event.key === 'ArrowUp';
    const arrowLeftKeyPressed = event.key === 'ArrowLeft';
    const arrowRightKeyPressed = event.key === 'ArrowRight';

    if (arrowLeftKeyPressed || arrowRightKeyPressed) {
      updateCursorPosition();

      return;
    }

    const hasAutocompleteSuggestions = !isEmpty(autoCompleteSuggestions);
    const suggestionCount = length(autoCompleteSuggestions);

    if (arrowDownKeyPressed && hasAutocompleteSuggestions) {
      event.preventDefault();
      const newIndex = inc(selectedSuggestionIndex);

      setSelectedSuggestionIndex(newIndex >= suggestionCount ? 0 : newIndex);

      return;
    }

    if (arrowUpKeyPressed && hasAutocompleteSuggestions) {
      event.preventDefault();
      const newIndex = dec(selectedSuggestionIndex);

      setSelectedSuggestionIndex(newIndex < 0 ? suggestionCount - 1 : newIndex);

      return;
    }

    if (escapeKeyPressed) {
      closeSuggestionPopover();
      setAutoCompleteSuggestions([]);

      return;
    }

    const isSearchFieldFocusedAndEnterKeyPressed =
      enterKeyPressed && isSearchFieldFocus;

    const canAcceptSuggestion =
      tabKeyPressed || isSearchFieldFocusedAndEnterKeyPressed;

    if (canAcceptSuggestion && hasAutocompleteSuggestions) {
      event.preventDefault();
      acceptAutocompleteSuggestionAtIndex(selectedSuggestionIndex);

      return;
    }

    if (enterKeyPressed) {
      applyCurrentFilter();
      setAutocompleteAnchor(null);
      searchRef?.current?.blur();
    }
  };

  const prepareSearch = (event): void => {
    const { value } = event.target;

    setSearch(value);

    setNewFilter(t);
  };

  const closeSuggestionPopover = (): void => {
    setAutocompleteAnchor(null);
  };

  const blurInput = (): void => {
    setIsSearchFieldFocused(false);
    clearDebounceDynamicSuggestions();
  };

  const dynamicCriteriaParameters = getDynamicCriteriaParametersAndValue({
    cursorPosition,
    newSelectableCriterias,
    search
  });

  const isDynamicCriteria = isDefined(dynamicCriteriaParameters);

  const memoProps = [
    currentFilter,
    search,
    cursorPosition,
    autoCompleteSuggestions,
    open,
    selectedSuggestionIndex,
    currentFilter,
    isDynamicCriteria,
    sendingDynamicCriteriaValueRequests,
    user,
    isCriteriasPanelOpen
  ];

  return (
    <MemoizedFilter
      content={
        <div className={classes.wrapper}>
          <FilterViews />
          <div className={classes.container}>
            <ClickAwayListener onClickAway={closeSuggestionPopover}>
              <div data-testid={labelSearchBar}>
                <Box className={classes.searchbarContainer}>
                  <div className={classes.searchFieldWrap}>
                    <div
                      className={cx({
                        [classes.hidden]: showChipsOverlay && chipsExpanded
                      })}
                    >
                      <SearchField
                        disabled={isCriteriasPanelOpen}
                        EndAdornment={renderEndAdornmentFilter(clearFilters)}
                        fullWidth
                        inputRef={searchRef as RefObject<HTMLInputElement>}
                        onBlur={blurInput}
                        onChange={prepareSearch}
                        onClick={(): void => {
                          setCursorPosition(
                            searchRef?.current?.selectionStart || 0
                          );
                        }}
                        onFocus={(): void => setIsSearchFieldFocused(true)}
                        onKeyDown={inputKey}
                        placeholder={t(labelSearch) as string}
                        value={search}
                      />
                    </div>
                    {showChipsOverlay && (
                      // biome-ignore lint/a11y/noStaticElementInteractions: overlay only redirects focus to the search input behind it
                      <div
                        className={cx(classes.chipsOverlay, {
                          [classes.chipsOverlayExpanded]: chipsExpanded
                        })}
                        onMouseDown={
                          chipsExpanded ? undefined : focusSearchField
                        }
                      >
                        <span className={classes.chipsOverlayIcon}>
                          <IconSearch fontSize="small" />
                        </span>
                        <FilterChips
                          expanded={chipsExpanded}
                          inline
                          onToggleExpand={toggleChipsExpand}
                        />
                      </div>
                    )}
                  </div>
                  <SearchHelp />
                  <Button
                    data-testid={labelMoreFilters}
                    icon={<TuneIcon fontSize="small" />}
                    iconVariant="start"
                    onClick={toggleCriteriasPanel}
                    size="small"
                    variant="ghost"
                  >
                    {t(labelMoreFilters)}
                  </Button>
                </Box>
                <Popper
                  anchorEl={autocompleteAnchor}
                  className={classes.autocompletePopper}
                  open={open}
                  style={{
                    width: searchRef?.current?.clientWidth
                  }}
                >
                  <Paper square>
                    {isDynamicCriteria &&
                      sendingDynamicCriteriaValueRequests && (
                        <MenuItem className={classes.loader}>
                          <CircularProgress size={20} />
                        </MenuItem>
                      )}
                    {autoCompleteSuggestions.map((suggestion, index) => {
                      return (
                        <MenuItem
                          key={suggestion}
                          onClick={(): void => {
                            acceptAutocompleteSuggestionAtIndex(index);
                            searchRef?.current?.focus();
                          }}
                          selected={index === selectedSuggestionIndex}
                        >
                          {suggestion}
                        </MenuItem>
                      );
                    })}
                  </Paper>
                </Popper>
              </div>
            </ClickAwayListener>
          </div>
          <Suspense fallback={null}>
            <Criterias searchData={{ search, setSearch }} />
          </Suspense>
        </div>
      }
      memoProps={memoProps}
    />
  );
};

export default Filter;
