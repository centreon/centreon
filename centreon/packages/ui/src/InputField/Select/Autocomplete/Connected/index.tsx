import { CircularProgress, useTheme } from '@mui/material';
import type { AutocompleteRenderOptionState } from '@mui/material/Autocomplete';

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
import {
  type HTMLAttributes,
  type ReactElement,
  useCallback,
  useEffect,
  useState
} from 'react';
import type { JsonDecoder } from 'ts.data.json';

import type { ListingMapModel, ListingModel, SelectEntry } from '../../../..';
import type {
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
import type { Props as AutocompleteFieldProps } from '..';

interface OptionResult<T> {
  result: Array<T>;
  limit: number;
  total: number;
}

export interface GetEndpointParams {
  page: number;
  search?: SearchParameter;
}

export interface ConnectedAutoCompleteFieldProps<TData> {
  allowUniqOption?: boolean;
  baseEndpoint?: string;
  changeIdValue?: (item: TData) => number | string;
  exclusionOptionProperty?: keyof SelectEntry;
  field: string;
  getEndpoint: (params: GetEndpointParams) => string;
  decoder?: JsonDecoder.Decoder<ListingModel<TData> | ListingMapModel<TData>>;
  getRenderedOptionText?: (option: TData) => ReactElement | string;
  getRequestHeaders?: HeadersInit;
  initialPage?: number;
  labelKey?: string;
  queryKey?: string;
  searchConditions?: Array<ConditionsSearchParameter>;
}

// biome-ignore lint/suspicious/noExplicitAny: HOC accepts varied AutocompleteField shapes
type AutocompleteLikeComponent = (props: any) => ReactElement;

const ConnectedAutocompleteField = (
  AutocompleteField: AutocompleteLikeComponent,
  multiple: boolean
): (<TData extends { name: string }>(
  props: ConnectedAutoCompleteFieldProps<TData> &
    Omit<AutocompleteFieldProps, 'options'>
) => ReactElement) => {
  const InnerConnectedAutocompleteField = <TData extends { name: string }>({
    initialPage = 1,
    getEndpoint,
    decoder,
    field,
    labelKey,
    open,
    exclusionOptionProperty = 'id',
    searchConditions = [],
    getRenderedOptionText = (option): string => option?.name?.toString(),
    getRequestHeaders,
    displayOptionThumbnail,
    queryKey,
    allowUniqOption,
    baseEndpoint,
    changeIdValue,
    ...props
  }: ConnectedAutoCompleteFieldProps<TData> &
    Omit<AutocompleteFieldProps, 'options'>): ReactElement => {
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
      functionToDebounce: (value: unknown): void => {
        setSearchParameter(getSearchParameter(value as string));
        setPage(1);
      },
      memoProps: [page, searchConditions],
      wait: 500
    });

    const theme = useTheme();

    const { fetchQuery, isFetching, prefetchNextPage, data } = useFetchQuery<
      ListingModel<TData> | ListingMapModel<TData>
    >({
      baseEndpoint,
      decoder,
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
        enabled: false,
        gcTime: 0,
        staleTime: 0,
        suspense: false
      }
    });

    const getOptionResult = useCallback(
      (
        newOptions: ListingModel<TData> | ListingMapModel<TData>
      ): OptionResult<TData> => {
        if ('result' in newOptions)
          return {
            limit: newOptions.meta.limit || 1,
            result: newOptions.result || [],
            total: newOptions.meta.total || 1
          };
        if ('content' in newOptions)
          return {
            limit: newOptions.size || 1,
            result: newOptions.content || [],
            total: newOptions.totalElements || 1
          };

        return {
          limit: 1,
          result: [],
          total: 1
        };
      },
      []
    );

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
        field,
        operator: '$and',
        values: {
          $ni: map(
            prop(exclusionOptionProperty),
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
        operator: '$and',
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

    const changeText = (event: React.ChangeEvent<HTMLInputElement>): void => {
      debounce(event.target.value);
    };

    const renderOptions = (
      renderProps: HTMLAttributes<HTMLLIElement>,
      option: SelectEntry,
      { selected }: AutocompleteRenderOptionState
    ): ReactElement => {
      const { value } = props;

      const lastValue = Array.isArray(value) ? last(value) : value;

      const isLastValueWithoutOptions =
        equals(option as unknown)(lastValue as unknown) && isEmpty(options);
      const lastOption = last(options);

      const isLastOption = equals(lastOption as unknown)(option as unknown);

      const canTriggerInfiniteScroll = isLastOption && page <= maxPage;

      const ref = canTriggerInfiniteScroll ? { ref: lastOptionRef } : {};

      const optionText = getRenderedOptionText(option as unknown as TData);

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

    const renameKey = useCallback(
      ({
        object,
        key,
        newKey
      }: {
        object: TData;
        key: string;
        newKey: string;
      }): Partial<TData> => {
        const oldKeyValue = (object as Record<string, unknown>)[key];
        const newObject = {
          ...object,
          [newKey]: oldKeyValue
        } as Record<string, unknown>;

        return omit([key], newObject) as Partial<TData>;
      },
      []
    );

    const fetchOptionsAndPrefetchNextOptions = useCallback((): void => {
      fetchQuery().then((newOptions) => {
        const isError = has('isError', newOptions);

        if (isError) {
          return;
        }

        const moreOptions = page > 1 ? options : [];

        const { result, limit, total } = getOptionResult(newOptions);

        const formattedList = changeIdValue
          ? result.map((item) => ({
              ...item,
              id: changeIdValue(item)
            }))
          : result;

        if (!isEmpty(labelKey) && !isNil(labelKey)) {
          const list = formattedList.map((item) =>
            renameKey({ key: labelKey, newKey: 'name', object: item })
          );
          setOptions(moreOptions.concat(list as Array<TData>));

          return;
        }
        setOptions(moreOptions.concat(formattedList));

        setOptions(moreOptions.concat(formattedList as Array<TData>));

        const newMaxPage = Math.ceil(total / limit);

        setMaxPage(newMaxPage);
        if (equals(newMaxPage, page)) {
          return;
        }

        prefetchNextPage({
          getPrefetchQueryKey: (newPage: number) => [
            `autocomplete-${props.label}`,
            newPage,
            searchParameter
          ],
          page
        });
      });
    }, [
      changeIdValue,
      fetchQuery,
      getOptionResult,
      labelKey,
      options,
      page,
      prefetchNextPage,
      props.label,
      renameKey,
      searchParameter
    ]);

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
    }, [optionsOpen, initialPage, JSON.stringify(searchConditions)]);

    useEffect(() => {
      setSearchParameter(
        !isEmpty(searchConditions)
          ? { conditions: searchConditions }
          : undefined
      );
    }, [...useDeepCompare([searchConditions])]);

    useEffect(() => {
      if (!autocompleteChangedValue) {
        return;
      }
      setSearchParameter(undefined);
    }, [autocompleteChangedValue]);

    useEffect(() => {
      if (!optionsOpen) {
        return;
      }

      fetchOptionsAndPrefetchNextOptions();
    }, [optionsOpen, page, searchParameter]);

    const handleChange = (
      _: React.SyntheticEvent,
      value: SelectEntry | Array<SelectEntry> | null
    ): void => {
      setAutocompleteChangedValue(value as Array<SelectEntry> | undefined);
    };

    return (
      <AutocompleteField
        filterOptions={(opt: Array<SelectEntry>): Array<SelectEntry> => opt}
        loading={isFetching}
        onChange={handleChange as unknown as AutocompleteFieldProps['onChange']}
        onClose={(): void => setOptionsOpen(false)}
        onOpen={(): void => setOptionsOpen(true)}
        onTextChange={changeText}
        options={
          (allowUniqOption
            ? uniqBy(getRenderedOptionText, options)
            : options) as unknown as Array<SelectEntry>
        }
        renderOption={renderOptions}
        total={
          (data && 'meta' in data ? data.meta.total : undefined) ||
          (data && 'totalElements' in data ? data.totalElements : undefined) ||
          1
        }
        {...props}
      />
    );
  };

  return InnerConnectedAutocompleteField;
};

export default ConnectedAutocompleteField;
