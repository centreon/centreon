import * as React from 'react';

import { equals, prop, last, isEmpty } from 'ramda';

import {
  Typography,
  Checkbox,
  makeStyles,
  CircularProgress,
  useTheme,
  FormControlLabel,
} from '@material-ui/core';
import debounce from '@material-ui/core/utils/debounce';

import { Props as AutocompleteFieldProps } from '..';
import useRequest from '../../../../api/useRequest';
import { getData } from '../../../../api';
import useIntersectionObserver from '../../../../utils/useIntersectionObserver';
import { ListingModel } from '../../../..';

export interface ConnectedAutoCompleteFieldProps {
  field: string;
  getEndpoint: ({ search, page }) => string;
  initialPage: number;
  search?: Record<string, unknown>;
}

type SearchDebounce = (value: string) => void;

const useStyles = makeStyles((theme) => ({
  checkbox: {
    marginRight: theme.spacing(1),
    padding: 0,
  },
}));

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean,
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <
    TData extends Record<string, unknown>
  >({
    initialPage = 1,
    getEndpoint,
    field,
    search,
    ...props
  }: ConnectedAutoCompleteFieldProps &
    Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
    const [options, setOptions] = React.useState<Array<TData>>([]);
    const [optionsOpen, setOptionsOpen] = React.useState<boolean>(false);
    const [searchValue, setSearchValue] = React.useState<string>('');
    const [page, setPage] = React.useState(1);
    const [maxPage, setMaxPage] = React.useState(initialPage);

    const classes = useStyles();
    const theme = useTheme();

    const { sendRequest, sending } = useRequest<ListingModel<TData>>({
      request: getData,
    });

    const loadOptions = ({ endpoint, loadMore = false }) => {
      sendRequest(endpoint).then(({ result, meta }) => {
        const moreOptions = loadMore ? options : [];
        setOptions(moreOptions.concat(result));

        const total = prop('total', meta) || 1;
        const limit = prop('limit', meta) || 1;

        setMaxPage(Math.ceil(total / limit));
      });
    };

    const lastItemElementRef = useIntersectionObserver({
      action: () => setPage(page + 1),
      loading: sending,
      maxPage,
      page,
    });

    const getSearchOption = (value: string) => {
      if (isEmpty(value)) {
        return search;
      }

      return {
        ...(search || {}),
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
              search: getSearchOption(value),
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

    const openOptions = (): void => {
      setOptionsOpen(true);
    };

    const closeOptions = (): void => {
      setOptionsOpen(false);
    };

    const renderOptions = (option, { selected }): JSX.Element => {
      const isLastElement = equals(last(options))(option);
      const refProp = isLastElement ? { ref: lastItemElementRef } : {};

      const checkbox = (
        <Checkbox
          checked={selected}
          className={classes.checkbox}
          color="primary"
          size="small"
        />
      );

      return (
        <div style={{ width: '100%' }}>
          <div>
            {multiple ? (
              <FormControlLabel
                control={checkbox}
                label={<Typography variant="body2">{option.name}</Typography>}
                labelPlacement="end"
                {...refProp}
              />
            ) : (
              <Typography variant="body2" {...refProp}>
                {option.name}
              </Typography>
            )}
          </div>
          {isLastElement && page > 1 && sending && (
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
        endpoint: getEndpoint({ page, search: getSearchOption(searchValue) }),
        loadMore: page > 1,
      });
    }, [optionsOpen, page]);

    return (
      <AutocompleteField
        filterOptions={(opt) => opt}
        loading={sending}
        options={options}
        renderOption={renderOptions}
        onClose={closeOptions}
        onOpen={openOptions}
        onTextChange={changeText}
        {...props}
      />
    );
  };

  return InnerConnectedAutocompleteField;
};

export default ConnectedAutocompleteField;
