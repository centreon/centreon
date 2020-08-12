import * as React from 'react';

import { equals, path, append, last, isEmpty } from 'ramda';

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

interface Props {
  getEndpoint: ({ search, page }) => string;
  field: string;
  initialPage: number;
  paginationPath?: Array<string>;
}

type SearchDebounce = (value: string) => void;

const useStyles = makeStyles((theme) => ({
  checkbox: {
    padding: 0,
    marginRight: theme.spacing(1),
  },
}));

const ConnectedAutocompleteField = (
  AutocompleteField: (props) => JSX.Element,
  multiple: boolean,
): ((props) => JSX.Element) => {
  const InnerConnectedAutocompleteField = <
    TData extends Record<string, unknown>
  >({
    initialPage,
    getEndpoint,
    field,
    paginationPath = [],
    ...props
  }: Props & Omit<AutocompleteFieldProps, 'options'>): JSX.Element => {
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

    const getPaginationProperty = ({ meta, prop }): number | undefined =>
      path(append(prop, paginationPath), meta);

    const loadOptions = ({ endpoint, loadMore = false }) => {
      sendRequest(endpoint).then(({ result, meta }) => {
        const moreOptions = loadMore ? options : [];
        setOptions(moreOptions.concat(result));

        const total = getPaginationProperty({ meta, prop: 'total' }) || 1;
        const limit = getPaginationProperty({ meta, prop: 'limit' }) || 1;

        setMaxPage(Math.ceil(total / limit));
      });
    };

    const lastItemElementRef = useIntersectionObserver({
      maxPage,
      page,
      loading: sending,
      action: () => setPage(page + 1),
    });

    const getSearchOption = (value: string) => {
      if (isEmpty(value)) {
        return undefined;
      }

      return {
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
              search: getSearchOption(value),
              page: initialPage,
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
          color="primary"
          size="small"
          checked={selected}
          className={classes.checkbox}
        />
      );

      return (
        <div style={{ width: '100%' }}>
          <div>
            {multiple ? (
              <FormControlLabel
                control={checkbox}
                label={option.name}
                labelPlacement="end"
                {...refProp}
              />
            ) : (
              <Typography {...refProp}>{option.name}</Typography>
            )}
          </div>
          {isLastElement && page > 1 && sending && (
            <div style={{ width: '100%', textAlign: 'center' }}>
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
        endpoint: getEndpoint({ search: getSearchOption(searchValue), page }),
        loadMore: page > 1,
      });
    }, [optionsOpen, page]);

    return (
      <AutocompleteField
        onOpen={openOptions}
        onClose={closeOptions}
        options={options}
        onTextChange={changeText}
        loading={sending}
        renderOption={renderOptions}
        filterOptions={(opt) => opt}
        {...props}
      />
    );
  };

  return InnerConnectedAutocompleteField;
};

export default ConnectedAutocompleteField;
