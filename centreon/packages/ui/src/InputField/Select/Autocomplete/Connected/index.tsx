import { useEffect, useState } from 'react';

import {
  equals,
  has,
  isEmpty,
  isNil,
  last,
  map,
  not,
  omit,
  pipe,
  prop,
  uniqBy
} from 'ramda';

import { CircularProgress, useTheme } from '@mui/material';

import { Props as AutocompleteFieldProps } from '..';
import { ListingModel, SelectEntry } from '../../../..';
import {
  ConditionsSearchParameter,
  SearchParameter
} from '../../../../api/buildListingEndpoint/models';
import useFetchQuery from '../../../../api/useFetchQuery';
import {
  useDebounce,
  useDeepCompare,
  useIntersectionObserver
} from '../../../../utils';
import Option from '../../Option';

export interface ConnectedAutoCompleteFieldProps<TData> {
  allowUniqOption?: boolean;
  baseEndpoint?: string;
  changeIdValue: (item: TData) => number | string;
  conditionField?: keyof SelectEntry;
  field: string;
  getEndpoint: ({ search, page }) => string;
  getRenderedOptionText: (option: TData) => string;
  getRequestHeaders?: HeadersInit;
  initialPage: number;
  labelKey?: string;
  queryKey?: string;
  searchConditions?: Array<ConditionsSearchParameter>;
}

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <TData extends { name: string }>({
    initialPage = 1,
    getEndpoint,
    field,
    labelKey,
    open,
    conditionField = 'name',
    searchConditions = [],
    getRenderedOptionText = (option): string => option.name?.toString(),
    getRequestHeaders,
    displayOptionThumbnail,
    queryKey,
    allowUniqOption,
    baseEndpoint,
    changeIdValue,
    ...props
  }: ConnectedAutoCompleteFieldProps<TData> &
    Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
    const [options, setOptions] = useState<Array<TData>>([]);
    const [page, setPage] = useState(1);
    const [maxPage, setMaxPage] = useState(initialPage);
    const [optionsOpen, setOptionsOpen] = useState(open || false);
    const [searchParameter, setSearchParameter] = useState<
      SearchParameter | undefined
    >(undefined);

    const [autocompleteChangedValue, setAutocompleteChangedValue] =
      useState<Array<SelectEntry>>();
    const debounce = useDebounce({
      functionToDebounce: (value): void => {
        setSearchParameter(getSearchParameter(value));
        setPage(1);
      },
      memoProps: [page, searchConditions],
      wait: 500
    });

    const theme = useTheme();

    const { fetchQuery, isFetching, prefetchNextPage } = useFetchQuery<
      ListingModel<TData>
    >({
      baseEndpoint,
      fetchHeaders: getRequestHeaders,
      getEndpoint: (params) => {
        return getEndpoint({
          page: params?.page || page,
          search: searchParameter
        });
      },
      getQueryKey: () => [
        `autocomplete-${queryKey || props.label}`,
        page,
        searchParameter
      ],
      isPaginated: true,
      queryOptions: {
        cacheTime: 0,
        enabled: false,
        staleTime: 0,
        suspense: false
      }
    });

    const lastOptionRef = useIntersectionObserver({
      action: () => setPage(page + 1),
      loading: isFetching,
      maxPage,
      page
    });

    const getExcludeSelectedValueCondition = ():
      | ConditionsSearchParameter
      | undefined => {
      const { value: selectedValue } = props;

      if (isEmpty(selectedValue || [])) {
        return undefined;
      }

      const selectedValues = Array.isArray(selectedValue)
        ? selectedValue
        : [selectedValue];

      return {
        field: conditionField,
        values: {
          $ni: map(
            prop(conditionField),
            selectedValues as Array<
              Record<keyof SelectEntry, string | undefined>
            >
          ) as Array<string>
        }
      };
    };

    const getSearchedValueCondition = (
      searchedValue: string
    ): ConditionsSearchParameter | undefined => {
      if (isEmpty(searchedValue)) {
        return undefined;
      }

      return {
        field,
        values: {
          $lk: `%${searchedValue}%`
        }
      };
    };

    const getSearchParameter = (value: string): SearchParameter | undefined => {
      const excludeSelectedValueCondition = getExcludeSelectedValueCondition();
      const searchedValueCondition = getSearchedValueCondition(value);

      const conditions = [
        excludeSelectedValueCondition,
        searchedValueCondition,
        ...searchConditions
      ].filter(pipe(isNil, not)) as Array<ConditionsSearchParameter>;

      if (isEmpty(conditions)) {
        return undefined;
      }

      return {
        conditions
      };
    };

    const changeText = (event): void => {
      debounce(event.target.value);
    };

    const renderOptions = (renderProps, option, { selected }): JSX.Element => {
      const { value } = props;

      const lastValue = Array.isArray(value) ? last(value) : value;

      const isLastValueWithoutOptions =
        equals(option)(lastValue) && isEmpty(options);
      const lastOption = last(options);

      const isLastOption = equals(lastOption)(option);

      const canTriggerInfiniteScroll = isLastOption && page <= maxPage;

      const ref = canTriggerInfiniteScroll ? { ref: lastOptionRef } : {};

      const optionText = getRenderedOptionText(option);

      const optionProps = {
        checkboxSelected: multiple ? selected : undefined,
        thumbnailUrl: displayOptionThumbnail ? option.url : undefined
      };

      return (
        <div key={option.id} style={{ width: '100%' }}>
          <li {...renderProps}>
            <Option {...optionProps} {...ref}>
              {optionText}
            </Option>
          </li>

          {(isLastValueWithoutOptions || isLastOption) && isFetching && (
            <div style={{ textAlign: 'center', width: '100%' }}>
              <CircularProgress size={theme.spacing(2.5)} />
            </div>
          )}
        </div>
      );
    };

    const renameKey = ({ object, key, newKey }): Partial<TData> => {
      const oldKeyValue = object[key];
      const newObject = { ...object, [newKey]: oldKeyValue };

      return omit([key], newObject);
    };

    const fetchOptionsAndPrefetchNextOptions = (): void => {
      fetchQuery().then((newOptions) => {
        const isError = has('isError', newOptions);

        if (isError) {
          return;
        }

        const moreOptions = page > 1 ? options : [];

        const formattedList = changeIdValue
          ? newOptions.result.map((item) => ({
              ...item,
              id: changeIdValue(item)
            }))
          : newOptions.result;

        if (!isEmpty(labelKey) && !isNil(labelKey)) {
          const list = formattedList.map((item) =>
            renameKey({ key: labelKey, newKey: 'name', object: item })
          );
          setOptions(moreOptions.concat(list as Array<TData>));

          return;
        }
        setOptions(moreOptions.concat(formattedList));

        setOptions(moreOptions.concat(formattedList as Array<TData>));

        const total = prop('total', newOptions.meta) || 1;
        const limit = prop('limit', newOptions.meta) || 1;

        const newMaxPage = Math.ceil(total / limit);

        setMaxPage(newMaxPage);
        if (equals(newMaxPage, page)) {
          return;
        }

        prefetchNextPage({
          getPrefetchQueryKey: (newPage) => [
            `autocomplete-${props.label}`,
            newPage,
            searchParameter
          ],
          page
        });
      });
    };

    useEffect(() => {
      if (!optionsOpen) {
        setOptions([]);
        setPage(initialPage);
        setSearchParameter(
          !isEmpty(searchConditions)
            ? { conditions: searchConditions }
            : undefined
        );
      }
    }, [optionsOpen]);

    useEffect(
      () => {
        setSearchParameter(
          !isEmpty(searchConditions)
            ? { conditions: searchConditions }
            : undefined
        );
      },
      useDeepCompare([searchConditions])
    );

    useEffect(() => {
      if (!autocompleteChangedValue && !props?.value) {
        return;
      }
      setSearchParameter(undefined);
    }, [autocompleteChangedValue, props?.value]);

    useEffect(() => {
      if (!optionsOpen) {
        return;
      }

      fetchOptionsAndPrefetchNextOptions();
    }, [optionsOpen, page, searchParameter]);

    return (
      <AutocompleteField
        filterOptions={(opt): SelectEntry => opt}
        loading={isFetching}
        open={optionsOpen}
        options={
          allowUniqOption ? uniqBy(getRenderedOptionText, options) : options
        }
        renderOption={renderOptions}
        onChange={(_, value) => setAutocompleteChangedValue(value)}
        onClose={(): void => setOptionsOpen(false)}
        onOpen={(): void => setOptionsOpen(true)}
        onTextChange={changeText}
        {...props}
      />
    );
  };

  return InnerConnectedAutocompleteField;
};

export default ConnectedAutocompleteField;
