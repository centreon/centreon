<<<<<<< HEAD
import {
  lazy,
  useRef,
  useState,
  useEffect,
  KeyboardEvent,
  Suspense,
  RefObject,
} from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import {
  isEmpty,
  propEq,
  pick,
  find,
  equals,
  last,
  inc,
  length,
  dec,
  isNil,
  not,
  map,
  difference,
  pluck,
  concat,
  pipe,
  dropLast,
  or,
  remove,
} from 'ramda';
import { useTranslation } from 'react-i18next';
<<<<<<< HEAD
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { useAtom } from 'jotai';

import CloseIcon from '@mui/icons-material/Close';
import {
  CircularProgress,
  ClickAwayListener,
  MenuItem,
  Paper,
  Popper,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import { userAtom } from '@centreon/ui-context';
=======

import CloseIcon from '@material-ui/icons/Close';
import {
  CircularProgress,
  ClickAwayListener,
  makeStyles,
  MenuItem,
  Paper,
  Popper,
} from '@material-ui/core';

>>>>>>> centreon/dev-21.10.x
import {
  MemoizedFilter,
  SearchField,
  IconButton,
  getData,
  useRequest,
<<<<<<< HEAD
  LoadingSkeleton,
=======
>>>>>>> centreon/dev-21.10.x
} from '@centreon/ui';

import {
  labelStateFilter,
  labelSearch,
  labelNewFilter,
  labelMyFilters,
  labelClearFilter,
} from '../translatedLabels';
<<<<<<< HEAD

import FilterLoadingSkeleton from './FilterLoadingSkeleton';
=======
import { useResourceContext } from '../Context';

import SaveFilter from './Save';
import FilterLoadingSkeleton from './FilterLoadingSkeleton';
import Criterias from './Criterias';
>>>>>>> centreon/dev-21.10.x
import {
  standardFilterById,
  unhandledProblemsFilter,
  resourceProblemsFilter,
  allFilter,
} from './models';
<<<<<<< HEAD
=======
import SelectFilter from './Fields/SelectFilter';
>>>>>>> centreon/dev-21.10.x
import {
  getAutocompleteSuggestions,
  getDynamicCriteriaParametersAndValue,
  DynamicCriteriaParametersAndValues,
} from './Criterias/searchQueryLanguage';
<<<<<<< HEAD
import {
  applyCurrentFilterDerivedAtom,
  applyFilterDerivedAtom,
  clearFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  searchAtom,
  sendingFilterAtom,
  setNewFilterDerivedAtom,
} from './filterAtoms';

const renderClearFilter = (onClear) => (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={t(labelClearFilter)}
      data-testid={labelClearFilter}
      size="small"
      title={t(labelClearFilter)}
      onClick={onClear}
    >
      <CloseIcon color="action" fontSize="small" />
    </IconButton>
  );
};
=======

>>>>>>> centreon/dev-21.10.x
interface DynamicCriteriaResult {
  result: Array<{ name: string }>;
}

const useStyles = makeStyles((theme) => ({
  autocompletePopper: {
    zIndex: theme.zIndex.tooltip,
  },
  container: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1),
    gridTemplateColumns: 'auto 175px auto 1fr',
    width: '100%',
  },
  loader: { display: 'flex', justifyContent: 'center' },
}));

<<<<<<< HEAD
const SaveFilter = lazy(() => import('./Save'));
const SelectFilter = lazy(() => import('./Fields/SelectFilter'));
const Criterias = lazy(() => import('./Criterias'));

=======
>>>>>>> centreon/dev-21.10.x
const debounceTimeInMs = 500;

const isDefined = pipe(isNil, not);

const Filter = (): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

<<<<<<< HEAD
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
=======
  const {
    applyFilter,
    customFilters,
    customFiltersLoading,
    setSearch,
    setNewFilter,
    currentFilter,
    search,
    applyCurrentFilter,
    clearFilter,
  } = useResourceContext();

  const [isSearchFieldFocus, setIsSearchFieldFocused] = React.useState(false);
  const [autocompleteAnchor, setAutocompleteAnchor] =
    React.useState<HTMLDivElement | null>(null);
  const searchRef = React.useRef<HTMLInputElement>();
  const [autoCompleteSuggestions, setAutoCompleteSuggestions] = React.useState<
    Array<string>
  >([]);
  const [cursorPosition, setCursorPosition] = React.useState(0);
  const [selectedSuggestionIndex, setSelectedSuggestionIndex] =
    React.useState(0);
  const dynamicSuggestionsDebounceRef = React.useRef<NodeJS.Timeout | null>(
    null,
  );
>>>>>>> centreon/dev-21.10.x

  const {
    sendRequest: sendDynamicCriteriaValueRequests,
    sending: sendingDynamicCriteriaValueRequests,
  } = useRequest<DynamicCriteriaResult>({
    request: getData,
  });

<<<<<<< HEAD
  const [search, setSearch] = useAtom(searchAtom);
  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);
  const sendingFilter = useAtomValue(sendingFilterAtom);
  const user = useAtomValue(userAtom);
  const applyCurrentFilter = useUpdateAtom(applyCurrentFilterDerivedAtom);
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);
  const setNewFilter = useUpdateAtom(setNewFilterDerivedAtom);
  const clearFilter = useUpdateAtom(clearFilterDerivedAtom);

=======
>>>>>>> centreon/dev-21.10.x
  const open = Boolean(autocompleteAnchor);

  const clearDebounceDynamicSuggestions = (): void => {
    if (dynamicSuggestionsDebounceRef.current) {
      clearInterval(dynamicSuggestionsDebounceRef.current as NodeJS.Timeout);
    }
  };

  const loadDynamicCriteriaSuggestion = ({
    criteria,
    values,
  }: DynamicCriteriaParametersAndValues): void => {
    const { buildAutocompleteEndpoint, autocompleteSearch } = criteria;

    const lastValue = last(values);

    const selectedValues = remove(-1, 1, values);

<<<<<<< HEAD
    sendDynamicCriteriaValueRequests({
      endpoint: buildAutocompleteEndpoint({
=======
    sendDynamicCriteriaValueRequests(
      buildAutocompleteEndpoint({
>>>>>>> centreon/dev-21.10.x
        limit: 5,
        page: 1,
        search: {
          conditions: [
            ...(autocompleteSearch?.conditions || []),
            not(isEmpty(selectedValues))
              ? {
                  field: 'name',
                  values: { $ni: selectedValues },
                }
              : {},
          ],
          regex: {
            fields: ['name'],
            value: lastValue,
          },
        },
      }),
<<<<<<< HEAD
    }).then(({ result }): void => {
=======
    ).then(({ result }): void => {
>>>>>>> centreon/dev-21.10.x
      const names = pluck('name', result);

      const lastValueEqualsToAResult = find(equals(lastValue), names);

      const notSelectedValues = difference(names, values);

      if (or(lastValueEqualsToAResult, isEmpty(names))) {
        const res = [
          ...notSelectedValues,
          ...map(concat(','), notSelectedValues),
        ];

        setAutoCompleteSuggestions(res);

        return;
      }

      setAutoCompleteSuggestions(names);
    });
  };

  const debounceDynamicSuggestions = (
    props: DynamicCriteriaParametersAndValues,
  ): void => {
    clearDebounceDynamicSuggestions();

    dynamicSuggestionsDebounceRef.current = setTimeout((): void => {
      loadDynamicCriteriaSuggestion(props);
    }, debounceTimeInMs);
  };

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    setSelectedSuggestionIndex(0);

    if (isEmpty(search.charAt(dec(cursorPosition)).trim())) {
      clearDebounceDynamicSuggestions();
      setAutoCompleteSuggestions([]);
      setAutocompleteAnchor(null);

      return;
    }

    const dynamicCriteriaParameters = getDynamicCriteriaParametersAndValue({
      cursorPosition,
      search,
    });

    if (isDefined(dynamicCriteriaParameters) && isSearchFieldFocus) {
      debounceDynamicSuggestions(
        dynamicCriteriaParameters as DynamicCriteriaParametersAndValues,
      );

      return;
    }

    clearDebounceDynamicSuggestions();
    setAutoCompleteSuggestions([]);

    setAutoCompleteSuggestions(
      getAutocompleteSuggestions({
        cursorPosition,
        search,
      }),
    );
  }, [search, cursorPosition]);

  const updateCursorPosition = (): void => {
    setCursorPosition(searchRef?.current?.selectionStart || 0);
  };

<<<<<<< HEAD
  useEffect(() => {
    updateCursorPosition();
  }, [searchRef?.current?.selectionStart]);

  useEffect(() => {
=======
  React.useEffect(() => {
    updateCursorPosition();
  }, [searchRef?.current?.selectionStart]);

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    const dynamicCriteriaParameters = getDynamicCriteriaParametersAndValue({
      cursorPosition,
      search,
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
<<<<<<< HEAD
    setNewFilter(t);
=======
    setNewFilter();
>>>>>>> centreon/dev-21.10.x

    const acceptedSuggestion = autoCompleteSuggestions[index];

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
      acceptedSuggestion.length,
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
          searchBeforeCompletedWord.trim(),
        );

    const suggestion = isEmpty(expressionAfterSeparator.trim())
      ? completedWord
      : acceptedSuggestion;

    const searchWithAcceptedSuggestion = [
      searchBeforeSuggestion,
      suggestion,
      searchAfterCompletedWord.trim() === '' ? '' : ' ',
      searchAfterCompletedWord,
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
        expressionToShiftToTheEnd,
      ].join(''),
    );
  };

<<<<<<< HEAD
  const inputKey = (event: KeyboardEvent): void => {
=======
  const inputKey = (event: React.KeyboardEvent): void => {
>>>>>>> centreon/dev-21.10.x
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

<<<<<<< HEAD
    setNewFilter(t);
=======
    setNewFilter();
>>>>>>> centreon/dev-21.10.x
  };

  const changeFilter = (event): void => {
    const filterId = event.target.value;

    const updatedFilter =
      standardFilterById[filterId] ||
      customFilters?.find(propEq('id', filterId));

    applyFilter(updatedFilter);
  };

  const translatedOptions = [
    unhandledProblemsFilter,
    resourceProblemsFilter,
    allFilter,
  ].map(({ id, name }) => ({ id, name: t(name) }));

  const customFilterOptions = isEmpty(customFilters)
    ? []
    : [
        {
          id: 'my_filters',
          name: t(labelMyFilters),
          type: 'header',
        },
        ...customFilters,
      ];

  const options = [
    { id: '', name: t(labelNewFilter) },
    ...translatedOptions,
    ...customFilterOptions,
  ];

  const canDisplaySelectedFilter = find(
    propEq('id', currentFilter.id),
    options,
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
    search,
  });

  const isDynamicCriteria = isDefined(dynamicCriteriaParameters);

  const memoProps = [
    customFilters,
<<<<<<< HEAD
    sendingFilter,
=======
    customFiltersLoading,
>>>>>>> centreon/dev-21.10.x
    search,
    cursorPosition,
    autoCompleteSuggestions,
    open,
    selectedSuggestionIndex,
    currentFilter,
    isDynamicCriteria,
    sendingDynamicCriteriaValueRequests,
<<<<<<< HEAD
    user,
=======
>>>>>>> centreon/dev-21.10.x
  ];

  return (
    <MemoizedFilter
      content={
        <div className={classes.container}>
<<<<<<< HEAD
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
                options={options.map(pick(['id', 'name', 'type']))}
                selectedOptionId={
                  canDisplaySelectedFilter ? currentFilter.id : ''
                }
                onChange={changeFilter}
              />
            </Suspense>
          )}
          <Suspense
            fallback={
              <LoadingSkeleton height={24} variant="circular" width={24} />
            }
          >
            <Criterias />
          </Suspense>
=======
          <SaveFilter />
          {customFiltersLoading ? (
            <FilterLoadingSkeleton />
          ) : (
            <SelectFilter
              ariaLabel={t(labelStateFilter)}
              options={options.map(pick(['id', 'name', 'type']))}
              selectedOptionId={
                canDisplaySelectedFilter ? currentFilter.id : ''
              }
              onChange={changeFilter}
            />
          )}
          <Criterias />
>>>>>>> centreon/dev-21.10.x
          <ClickAwayListener onClickAway={closeSuggestionPopover}>
            <div data-testid={labelClearFilter}>
              <SearchField
                fullWidth
<<<<<<< HEAD
                EndAdornment={renderClearFilter(clearFilter)}
                inputRef={searchRef as RefObject<HTMLInputElement>}
=======
                EndAdornment={(): JSX.Element => (
                  <IconButton
                    ariaLabel={t(labelClearFilter)}
                    data-testid={labelClearFilter}
                    size="small"
                    title={t(labelClearFilter)}
                    onClick={clearFilter}
                  >
                    <CloseIcon color="action" fontSize="small" />
                  </IconButton>
                )}
                inputRef={searchRef as React.RefObject<HTMLInputElement>}
>>>>>>> centreon/dev-21.10.x
                placeholder={t(labelSearch)}
                value={search}
                onBlur={blurInput}
                onChange={prepareSearch}
                onClick={(): void => {
                  setCursorPosition(searchRef?.current?.selectionStart || 0);
                }}
                onFocus={(): void => setIsSearchFieldFocused(true)}
                onKeyDown={inputKey}
              />
              <Popper
                anchorEl={autocompleteAnchor}
                className={classes.autocompletePopper}
                open={open}
                style={{
                  width: searchRef?.current?.clientWidth,
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
        </div>
      }
      memoProps={memoProps}
    />
  );
};

export default Filter;
