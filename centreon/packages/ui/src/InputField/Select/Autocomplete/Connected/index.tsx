import * as React from 'react';

import { equals, prop, last, isEmpty, map, isNil } from 'ramda';

import { Typography, CircularProgress, useTheme } from '@material-ui/core';
import debounce from '@material-ui/core/utils/debounce';

import { Props as AutocompleteFieldProps } from '..';
import useRequest from '../../../../api/useRequest';
import { getData } from '../../../../api';
import useIntersectionObserver from '../../../../utils/useIntersectionObserver';
import { ListingModel, SelectEntry } from '../../../..';
import Option from '../../Option';

export interface ConnectedAutoCompleteFieldProps<TData> {
  conditionField?: keyof SelectEntry;
  field: string;
  getEndpoint: ({ search, page }) => string;
  getRenderedOptionText: (option: TData) => string;
  initialPage: number;
  search?: Record<string, unknown>;
}

type SearchDebounce = (value: string) => void;

interface GetSearchConditionState {
  conditions: Array<{
    field: string;
    values: { $ni: Array<string | number | undefined> };
  }>;
}

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean,
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <TData extends { name: string }>({
    initialPage = 1,
    getEndpoint,
    field,
    search = {},
    open,
    conditionField = 'id',
    getRenderedOptionText = (option): string => option.name,
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
      sendRequest(endpoint).then(({ result, meta }) => {
        const moreOptions = loadMore ? options : [];

        setOptions(moreOptions.concat(result));

        const total = prop('total', meta) || 1;
        const limit = prop('limit', meta) || 1;

        setMaxPage(Math.ceil(total / limit));
      });
    };

    const lastOptionRef = useIntersectionObserver({
      action: () => setPage(page + 1),
      loading: sending,
      maxPage,
      page,
    });

    const getSearchCondition = (): GetSearchConditionState | undefined => {
      const { value: selectedValue } = props;

      if (isEmpty(selectedValue || [])) {
        return undefined;
      }

      const selectedValues = Array.isArray(selectedValue)
        ? selectedValue
        : [selectedValue];

      return {
        conditions: [
          {
            field: conditionField,
            values: {
              $ni: map(
                prop(conditionField),
                selectedValues as Array<
                  Record<keyof SelectEntry, string | number | undefined>
                >,
              ),
            },
          },
        ],
      };
    };

    const getSearchWithCondition = (): Record<string, unknown> | undefined => {
      const searchCondition = getSearchCondition();

      if (isNil(searchCondition)) {
        return equals(search, {}) ? undefined : search;
      }

      return { ...search, ...getSearchCondition() };
    };

    const getSearchParameter = (
      value: string,
    ): Record<string, unknown> | undefined => {
      if (isEmpty(value)) {
        return getSearchWithCondition();
      }

      return {
        ...getSearchWithCondition(),
        regex: {
          fields: [field],
          value,
        },
      };
    };

    const debouncedChangeText = React.useRef<SearchDebounce>(
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
    );

    const changeText = (event): void => {
      debouncedChangeText.current(event.target.value);
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
