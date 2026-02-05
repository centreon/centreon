import ArrowBackIcon from '@mui/icons-material/ArrowBackIosNew';
import ArrowForwardIcon from '@mui/icons-material/ArrowForwardIos';
import { CircularProgress, Link, Typography } from '@mui/material';

import { equals, isEmpty, isNil } from 'ramda';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import buildListingEndpoint from '../api/buildListingEndpoint';
import type { Listing } from '../api/models';
import useFetchQuery from '../api/useFetchQuery';
import IconButton from '../Button/Icon';
import {
  labelNextPage,
  labelNoResultFound,
  labelPreviousPage
} from '../Listing/translatedLabels';
import { truncate } from '../utils';
import { useStyles } from './Pagination.styles';

interface Props {
  api: {
    baseEndpoint: string;
    queryKey: Array<string>;
    searchConditions?;
  };
  labelHasNoElements?: string;
  onItemClick?: ({ id }: { id: number }) => void;
}

const limit = 6;

const Pagination = ({
  api: { baseEndpoint, queryKey, searchConditions },
  labelHasNoElements = labelNoResultFound,
  onItemClick
}: Props) => {
  const { t } = useTranslation();
  const { cx, classes } = useStyles();
  const [page, setPage] = useState(1);

  const { data, isLoading } = useFetchQuery<
    Listing<{ id: number; name: string }>
  >({
    getEndpoint: (parameters): string =>
      buildListingEndpoint({
        baseEndpoint: baseEndpoint,
        parameters: {
          ...parameters,
          limit,
          page,
          ...(searchConditions
            ? {
                search: {
                  conditions: searchConditions
                }
              }
            : {}),
          sort: { status: 'DESC' }
        }
      }),
    getQueryKey: () => [...queryKey, page],
    queryOptions: {
      suspense: false
    }
  });

  const pagesCount = Math.ceil(data?.meta.total / limit);
  const arePaginationComponentsDisplayed = !equals(pagesCount, 1);

  const hasNoElements = useMemo(
    () => isEmpty(data?.result) || isNil(data?.result),
    [data]
  );

  if (hasNoElements) {
    return (
      <div className={classes.notFound}>
        <Typography color="disabled">{t(labelHasNoElements)}</Typography>
      </div>
    );
  }

  return (
    <div className={classes.container}>
      <div className={classes.body}>
        {arePaginationComponentsDisplayed && (
          <div className={classes.arrowContainer}>
            <IconButton
              className={classes.icon}
              dataTestid={labelPreviousPage}
              disabled={equals(page, 1)}
              onClick={() => setPage(page - 1)}
            >
              <ArrowBackIcon className={classes.arrow} />
            </IconButton>
          </div>
        )}

        <div className={classes.content}>
          {isLoading ? (
            <CircularProgress color="inherit" size={25} />
          ) : (
            data?.result.map(({ id, name }) => (
              <Link
                className={cx({
                  [classes.item]: true,
                  [classes.link]: !!onItemClick
                })}
                key={id}
                onClick={() => onItemClick?.({ id })}
                variant="body2"
              >
                {truncate({
                  content: name,
                  maxLength: 25
                })}
              </Link>
            ))
          )}
        </div>

        {arePaginationComponentsDisplayed && (
          <div className={classes.arrowContainer}>
            <IconButton
              className={classes.icon}
              dataTestid={labelNextPage}
              disabled={equals(pagesCount, page)}
              onClick={() => setPage(page + 1)}
            >
              <ArrowForwardIcon className={classes.arrow} />
            </IconButton>
          </div>
        )}
      </div>

      {arePaginationComponentsDisplayed && (
        <Typography
          className={classes.page}
          variant="body2"
        >{`Page ${page}/${pagesCount}`}</Typography>
      )}
    </div>
  );
};

export default Pagination;
