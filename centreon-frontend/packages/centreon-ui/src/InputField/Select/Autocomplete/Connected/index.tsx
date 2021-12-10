import * as React from 'react';

import { equals, prop, last, isEmpty, map, isNil, pipe, not } from 'ramda';

import { Typography, CircularProgress, useTheme } from '@material-ui/core';
import { debounce } from '@material-ui/core/utils';

import { Props as AutocompleteFieldProps } from '..';
import useRequest from '../../../../api/useRequest';
import { getData } from '../../../../api';
import useIntersectionObserver from '../../../../utils/useIntersectionObserver';
import { ListingModel, SelectEntry } from '../../../..';
import Option from '../../Option';
import {
  ConditionsSearchParameter,
  SearchParameter,
} from '../../../../api/buildListingEndpoint/models';

export interface ConnectedAutoCompleteFieldProps<TData> {
  conditionField?: keyof SelectEntry;
  field: string;
  getEndpoint: ({ search, page }) => string;
  getRenderedOptionText: (option: TData) => string;
  getRequestHeaders?: Record<string, unknown>;
  initialPage: number;
  searchConditions?: Array<ConditionsSearchParameter>;
}

type SearchDebounce = (value: string) => void;

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean,
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <TData extends { name: string }>({
    initialPage = 1,
    getEndpoint,
    field,
    open,
    conditionField = 'id',
    searchConditions = [],
    getRenderedOptionText = (option): string => option.name,
    getRequestHeaders,
    ...props
  }: ConnectedAutoCompleteFieldProps<TData> &
    Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
    const [options, setOptions] = React.useState<Array<TData>>([]);
    const [searchValue, setSearchValue] = React.useState<string>('');
    const [page, setPage] = React.useState(1);
    const [maxPage, setMaxPage] = React.useState(initialPage);
    const [optionsOpen, setOptionsOpen] = React.useState(open || false);

    const theme = useTheme();

    const { sendRequest, sending } = useRequest<ListingModel<TData>>({
      request: getData,
    });

    const loadOptions = ({ endpoint, loadMore = false }): void => {
      sendRequest({ endpoint, headers: getRequestHeaders }).then(
        ({ result, meta }) => {
          const moreOptions = loadMore ? options : [];

          setOptions(moreOptions.concat(result));

          const total = prop('total', meta) || 1;
          const limit = prop('limit', meta) || 1;

          setMaxPage(Math.ceil(total / limit));
        },
      );
    };

    const lastOptionRef = useIntersectionObserver({
      action: () => setPage(page + 1),
      loading: sending,
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

    const debouncedChangeText = React.useCallback(
      debounce<SearchDebounce>((value): void => {
        if (page === initialPage) {
          loadOptions({
            endpoint: getEndpoint({
              page: initialPage,
              search: getSearchParameter(value),
            }),
          });
        }

        setPage(1);
      }, 500),
      [page, setPage, searchConditions],
    );

    const changeText = (event): void => {
      debouncedChangeText(event.target.value);
      setSearchValue(event.target.value);
    };

    const renderOptions = (option, { selected }): JSX.Element => {
      const { value } = props;

      const lastValue = Array.isArray(value) ? last(value) : value;

      const isLastValueWithoutOptions =
        equals(option)(lastValue) && isEmpty(options);
      const lastOption = last(options);

      const isLastOption = equals(lastOption)(option);

      const canTriggerInfiniteScroll = isLastOption && page <= maxPage;

      const ref = canTriggerInfiniteScroll ? { ref: lastOptionRef } : {};

      const optionText = getRenderedOptionText(option);

      return (
        <div style={{ width: '100%' }}>
          <div>
            {multiple ? (
              <Option checkboxSelected={selected} {...ref}>
                {optionText}
              </Option>
            ) : (
              <Typography variant="body2" {...ref}>
                {optionText}
              </Typography>
            )}
          </div>

          {(isLastValueWithoutOptions || isLastOption) && sending && (
            <div style={{ textAlign: 'center', width: '100%' }}>
              <CircularProgress size={theme.spacing(2.5)} />
            </div>
          )}
        </div>
      );
    };

    React.useEffect(() => {
      if (!optionsOpen) {
        setSearchValue('');
        setOptions([]);
        setPage(initialPage);

        return;
      }

      loadOptions({
        endpoint: getEndpoint({
          page,
          search: getSearchParameter(searchValue),
        }),
        loadMore: page > 1,
      });
    }, [optionsOpen, page]);

    return (
      <AutocompleteField
        filterOptions={(opt): SelectEntry => opt}
        loading={sending}
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
