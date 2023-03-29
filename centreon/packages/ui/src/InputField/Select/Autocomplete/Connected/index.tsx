import { useState, useEffect } from 'react';

import {
  equals,
  prop,
  last,
  isEmpty,
  map,
  isNil,
  pipe,
  not,
  has,
  omit,
} from 'ramda';

import { CircularProgress, useTheme } from '@mui/material';

import { Props as AutocompleteFieldProps } from '..';
import useIntersectionObserver from '../../../../utils/useIntersectionObserver';
import { ListingModel, SelectEntry } from '../../../..';
import Option from '../../Option';
import {
  ConditionsSearchParameter,
  SearchParameter,
} from '../../../../api/buildListingEndpoint/models';
import useDebounce from '../../../../utils/useDebounce';
import useFetchQuery from '../../../../api/useFetchQuery';
import { useDeepCompare } from '../../../../utils/useMemoComponent';

export interface ConnectedAutoCompleteFieldProps<TData> {
  conditionField?: keyof SelectEntry;
  field: string;
  getEndpoint: ({ search, page }) => string;
  getRenderedOptionText: (option: TData) => string;
  getRequestHeaders?: HeadersInit;
  initialPage: number;
  labelKey?: string;
  searchConditions?: Array<ConditionsSearchParameter>;
}
const t = 't';

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean,
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <TData extends { name: string }>({
    initialPage = 1,
    getEndpoint,
    field,
    labelKey,
    open,
    conditionField = 'id',
    searchConditions = [],
    getRenderedOptionText = (option): string => option.name?.toString(),
    getRequestHeaders,
    displayOptionThumbnail,
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
    const debounce = useDebounce({
      functionToDebounce: (value): void => {
        setSearchParameter(getSearchParameter(value));
        setPage(1);
      },
      memoProps: [page, searchConditions],
      wait: 500,
    });

    const theme = useTheme();

    const { fetchQuery, isFetching, prefetchNextPage } = useFetchQuery<
      ListingModel<TData>
    >({
      fetchHeaders: getRequestHeaders,
      getEndpoint: (params) => {
        return getEndpoint({
          page: params?.page || page,
          search: searchParameter,
        });
      },
      getQueryKey: () => [`autocomplete-${props.label}`, page, searchParameter],
      isPaginated: true,
      queryOptions: {
        enabled: false,
        suspense: false,
      },
    });

    const lastOptionRef = useIntersectionObserver({
      action: () => setPage(page + 1),
      loading: isFetching,
      maxPage,
      page,
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
            >,
          ) as Array<string>,
        },
      };
    };

    const getSearchedValueCondition = (
      searchedValue: string,
    ): ConditionsSearchParameter | undefined => {
      if (isEmpty(searchedValue)) {
        return undefined;
      }

      return {
        field,
        values: {
          $lk: `%${searchedValue}%`,
        },
      };
    };

    const getSearchParameter = (value: string): SearchParameter | undefined => {
      const excludeSelectedValueCondition = getExcludeSelectedValueCondition();
      const searchedValueCondition = getSearchedValueCondition(value);

      const conditions = [
        excludeSelectedValueCondition,
        searchedValueCondition,
        ...searchConditions,
      ].filter(pipe(isNil, not)) as Array<ConditionsSearchParameter>;

      if (isEmpty(conditions)) {
        return undefined;
      }

      return {
        conditions,
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
        thumbnailUrl: displayOptionThumbnail ? option.url : undefined,
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

        if (!isEmpty(labelKey) && !isNil(labelKey)) {
          const list = newOptions.result.map((item) =>
            renameKey({ key: labelKey, newKey: 'name', object: item }),
          );
          setOptions(moreOptions.concat(list as Array<TData>));

          return;
        }
        setOptions(moreOptions.concat(newOptions.result));

        setOptions(moreOptions.concat(newOptions.result as Array<TData>));

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
            searchParameter,
          ],
          page,
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
            : undefined,
        );
      }
    }, [optionsOpen]);

    useEffect(() => {
      setSearchParameter(
        !isEmpty(searchConditions)
          ? { conditions: searchConditions }
          : undefined,
      );
    }, useDeepCompare([searchConditions]));

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
        open={open}
        options={options}
        renderOption={renderOptions}
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
