import {
  KeyboardEvent,
  RefObject,
  Suspense,
  lazy,
  useEffect,
  useRef,
  useState
} from 'react';

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
  pick,
  pipe,
  pluck,
  propEq,
  remove,
  uniq
} from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import CloseIcon from '@mui/icons-material/Close';
import {
  Box,
  CircularProgress,
  ClickAwayListener,
  MenuItem,
  Paper,
  Popper
} from '@mui/material';

import {
  IconButton,
  LoadingSkeleton,
  Filter as MemoizedFilter,
  SearchField,
  SelectEntry,
  getData,
  useRequest
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelClearFilter,
  labelMyFilters,
  labelNewFilter,
  labelSearch,
  labelSearchBar,
  labelStateFilter
} from '../translatedLabels';

import {
  DynamicCriteriaParametersAndValues,
  getAutocompleteSuggestions,
  getDynamicCriteriaParametersAndValue,
  replaceMiddleSpace
} from './Criterias/searchQueryLanguage';
import FilterLoadingSkeleton from './FilterLoadingSkeleton';
import SearchHelp from './SearchHelp';
import { selectedStatusByResourceTypeAtom } from './criteriasNewInterface/basicFilter/atoms';
import { escapeRegExpSpecialChars } from './criteriasNewInterface/utils';
import {
  applyCurrentFilterDerivedAtom,
  applyFilterDerivedAtom,
  clearFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  isCriteriasPanelOpenAtom,
  searchAtom,
  sendingFilterAtom,
  setNewFilterDerivedAtom
} from './filterAtoms';
import {
  allFilter,
  resourceProblemsFilter,
  standardFilterById,
  unhandledProblemsFilter
} from './models';
import useBackToVisualizationByAll from './useBackToVisualizationByAll';
import useFilterByModule from './useFilterByModule';

const renderEndAdornmentFilter = (onClear) => (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  return (
    <div className={classes.End}>
      <IconButton
        ariaLabel={t(labelClearFilter) as string}
        data-testid={labelClearFilter}
        size="small"
        title={t(labelClearFilter) as string}
        onClick={onClear}
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
  End: {
    display: 'flex',
    flexDirection: 'row'
  },
  autocompletePopper: {
    zIndex: theme.zIndex.tooltip
  },
  container: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1),
    gridTemplateColumns: '1fr auto 175px',
    width: '100%'
  },
  loader: { display: 'flex', justifyContent: 'center' },
  searchbarContainer: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5)
  }
}));

const SaveFilter = lazy(() => import('./Edit/EditButton'));
const SelectFilter = lazy(() => import('./Fields/SelectFilter'));
const Criterias = lazy(() => import('./Criterias'));

const debounceTimeInMs = 500;

const isDefined = pipe(isNil, not);

const Filter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { newSelectableCriterias } = useFilterByModule();

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
  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);
  const sendingFilter = useAtomValue(sendingFilterAtom);
  const user = useAtomValue(userAtom);
  const isCriteriasPanelOpen = useAtomValue(isCriteriasPanelOpenAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const setNewFilter = useSetAtom(setNewFilterDerivedAtom);
  const clearFilter = useSetAtom(clearFilterDerivedAtom);
  const setSelectedStatusByResourceType = useSetAtom(
    selectedStatusByResourceTypeAtom
  );

  useBackToVisualizationByAll();

  const open = Boolean(autocompleteAnchor);

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

    const selectedValues = remove(-1, 1, values).map(escapeRegExpSpecialChars);

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
            value: escapeRegExpSpecialChars(lastValue || '')
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

  const changeFilter = (event): void => {
    const filterId = event.target.value;

    const updatedFilter =
      standardFilterById[filterId] ||
      customFilters?.find(propEq('id', filterId));

    applyFilter(updatedFilter);
  };

  const translatedOptions: Array<SelectEntry> = [
    unhandledProblemsFilter,
    resourceProblemsFilter,
    allFilter
  ].map(({ id, name }) => ({ id, name: t(name), testId: `Filter ${name}` }));

  const customFilterOptions: Array<SelectEntry> = isEmpty(customFilters)
    ? []
    : [
        {
          id: 'my_filters',
          name: t(labelMyFilters),
          type: 'header'
        },
        ...customFilters
      ];

  const options: Array<SelectEntry> = [
    { id: '', name: t(labelNewFilter) },
    ...translatedOptions,
    ...customFilterOptions
  ];

  const canDisplaySelectedFilter = find(
    propEq('id', currentFilter.id),
    options
  );

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
    customFilters,
    sendingFilter,
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
        <div className={classes.container}>
          <ClickAwayListener onClickAway={closeSuggestionPopover}>
            <div data-testid={labelSearchBar}>
              <Box className={classes.searchbarContainer}>
                <SearchField
                  fullWidth
                  EndAdornment={renderEndAdornmentFilter(clearFilters)}
                  disabled={isCriteriasPanelOpen}
                  inputRef={searchRef as RefObject<HTMLInputElement>}
                  placeholder={t(labelSearch) as string}
                  value={search}
                  onBlur={blurInput}
                  onChange={prepareSearch}
                  onClick={(): void => {
                    setCursorPosition(searchRef?.current?.selectionStart || 0);
                  }}
                  onFocus={(): void => setIsSearchFieldFocused(true)}
                  onKeyDown={inputKey}
                />
                <Suspense
                  fallback={
                    <LoadingSkeleton
                      height={24}
                      variant="circular"
                      width={24}
                    />
                  }
                >
                  <Criterias searchData={{ search, setSearch }} />
                </Suspense>
                <SearchHelp />
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
                  {isDynamicCriteria && sendingDynamicCriteriaValueRequests && (
                    <MenuItem className={classes.loader}>
                      <CircularProgress size={20} />
                    </MenuItem>
                  )}
                  {autoCompleteSuggestions.map((suggestion, index) => {
                    return (
                      <MenuItem
                        key={suggestion}
                        selected={index === selectedSuggestionIndex}
                        onClick={(): void => {
                          acceptAutocompleteSuggestionAtIndex(index);
                          searchRef?.current?.focus();
                        }}
                      >
                        {suggestion}
                      </MenuItem>
                    );
                  })}
                </Paper>
              </Popper>
            </div>
          </ClickAwayListener>
          <Suspense
            fallback={
              <LoadingSkeleton height={24} variant="circular" width={24} />
            }
          >
            <SaveFilter />
          </Suspense>
          {sendingFilter ? (
            <FilterLoadingSkeleton />
          ) : (
            <Suspense fallback={<FilterLoadingSkeleton />}>
              <SelectFilter
                ariaLabel={t(labelStateFilter)}
                options={options.map(pick(['id', 'name', 'type', 'testId']))}
                selectedOptionId={
                  canDisplaySelectedFilter ? currentFilter.id : ''
                }
                onChange={changeFilter}
              />
            </Suspense>
          )}
        </div>
      }
      memoProps={memoProps}
    />
  );
};

export default Filter;
