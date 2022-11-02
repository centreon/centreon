import { useState, useEffect } from 'react';

import { equals, prop, last, isEmpty, map, isNil, pipe, not } from 'ramda';

import { CircularProgress, useTheme } from '@mui/material';

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
import useDebounce from '../../../../utils/useDebounce';

export interface ConnectedAutoCompleteFieldProps<TData> {
  conditionField?: keyof SelectEntry;
  field: string;
  getEndpoint: ({ search, page }) => string;
  getRenderedOptionText: (option: TData) => string;
  getRequestHeaders?: Record<string, unknown>;
  initialPage: number;
  searchConditions?: Array<ConditionsSearchParameter>;
}

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
    displayOptionThumbnail,
    ...props
  }: ConnectedAutoCompleteFieldProps<TData> &
    Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
    const [options, setOptions] = useState<Array<TData>>([]);
    const [searchValue, setSearchValue] = useState<string>('');
    const [page, setPage] = useState(1);
    const [maxPage, setMaxPage] = useState(initialPage);
    const [optionsOpen, setOptionsOpen] = useState(open || false);
    const debounce = useDebounce({
      functionToDebounce: (value): void => {
        if (page === initialPage) {
          loadOptions({
            endpoint: getEndpoint({
              page: initialPage,
              search: getSearchParameter(value),
            }),
          });
        }

        setPage(1);
      },
      memoProps: [page, searchConditions],
      wait: 500,
    });

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

    const changeText = (event): void => {
      debounce(event.target.value);
      setSearchValue(event.target.value);
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

          {(isLastValueWithoutOptions || isLastOption) && sending && (
            <div style={{ textAlign: 'center', width: '100%' }}>
              <CircularProgress size={theme.spacing(2.5)} />
            </div>
          )}
        </div>
      );
    };

    useEffect(() => {
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
