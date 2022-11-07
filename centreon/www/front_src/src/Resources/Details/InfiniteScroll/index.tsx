<<<<<<< HEAD
import { RefObject, useEffect, useRef, useState } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import {
  always,
  isNil,
  isEmpty,
  cond,
  T,
  concat,
  gt,
  equals,
  not,
  length,
} from 'ramda';
import { useTranslation } from 'react-i18next';
<<<<<<< HEAD
import { useAtomValue } from 'jotai/utils';
=======
>>>>>>> centreon/dev-21.10.x

import {
  CircularProgress,
  Fab,
  Fade,
  LinearProgress,
<<<<<<< HEAD
  Tooltip,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
=======
  makeStyles,
  Tooltip,
} from '@material-ui/core';
import KeyboardArrowUpIcon from '@material-ui/icons/KeyboardArrowUp';
>>>>>>> centreon/dev-21.10.x

import { useIntersectionObserver, ListingModel } from '@centreon/ui';

import NoResultsMessage from '../NoResultsMessage';
<<<<<<< HEAD
import memoizeComponent from '../../memoizedComponent';
import { labelScrollToTop } from '../../translatedLabels';
import { selectedResourceIdAtom } from '../detailsAtoms';
import { ResourceDetails } from '../models';
=======
import { ResourceDetails } from '../models';
import { ResourceContext, useResourceContext } from '../../Context';
import memoizeComponent from '../../memoizedComponent';
import { labelScrollToTop } from '../../translatedLabels';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  container: {
    alignContent: 'flex-start',
    alignItems: 'center',
    display: 'grid',
    height: '100%',
    justifyItems: 'center',
    width: '100%',
  },
  entities: {
    display: 'grid',
    gridAutoFlow: 'row',
    gridGap: theme.spacing(1),
    gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
    width: '100%',
  },
  entitiesContainer: {
    paddingBottom: theme.spacing(0.5),
    width: '100%',
  },
  fab: {
    bottom: 0,
    display: 'flex',
    justifyContent: 'flex-end',
    marginRight: theme.spacing(1.5),
    position: 'sticky',
  },
  filter: {
    marginTop: theme.spacing(),
    width: '100%',
  },
  progress: {
    height: theme.spacing(0.5),
    marginBottom: theme.spacing(1),
    width: '100%',
  },
  scrollableContainer: {
    bottom: 0,
    left: 0,
    overflow: 'auto',
    padding: theme.spacing(2),
    paddingTop: theme.spacing(1),
    position: 'absolute',
    right: 0,
    top: 0,
  },
}));

interface Props<TEntity> {
  children: (props) => JSX.Element;
  details?: ResourceDetails;
  filter?: JSX.Element;
  limit: number;
  loading: boolean;
  loadingSkeleton: JSX.Element;
  preventReloadWhen?: boolean;
  reloadDependencies?: Array<unknown>;
  sendListingRequest?: (parameters: {
    atPage?: number;
  }) => Promise<ListingModel<TEntity>>;
}

<<<<<<< HEAD
const InfiniteScrollContent = <TEntity extends { id: number }>({
  limit,
  filter,
=======
type InfiniteScrollContentProps<TEntity> = Props<TEntity> &
  Pick<ResourceContext, 'selectedResourceId'>;

const InfiniteScrollContent = <TEntity extends { id: number }>({
  limit,
  filter,
  details,
>>>>>>> centreon/dev-21.10.x
  reloadDependencies = [],
  loadingSkeleton,
  loading,
  preventReloadWhen = false,
<<<<<<< HEAD
  sendListingRequest,
  children,
  details,
}: Props<TEntity>): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  const [entities, setEntities] = useState<Array<TEntity>>();
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loadingMoreEvents, setLoadingMoreEvents] = useState(false);
  const [isScrolling, setIsScrolling] = useState(false);
  const scrollableContainerRef = useRef<HTMLDivElement | undefined>();
  const preventScrollingRef = useRef(false);

  const selectedResourceId = useAtomValue(selectedResourceIdAtom);
=======
  selectedResourceId,
  sendListingRequest,
  children,
}: InfiniteScrollContentProps<TEntity>): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  const [entities, setEntities] = React.useState<Array<TEntity>>();
  const [page, setPage] = React.useState(1);
  const [total, setTotal] = React.useState(0);
  const [loadingMoreEvents, setLoadingMoreEvents] = React.useState(false);
  const [isScrolling, setIsScrolling] = React.useState(false);
  const scrollableContainerRef = React.useRef<HTMLDivElement | undefined>();
  const preventScrollingRef = React.useRef(false);
>>>>>>> centreon/dev-21.10.x

  const listEntities = (
    { atPage } = {
      atPage: page,
    },
  ): Promise<ListingModel<TEntity>> | undefined => {
    return sendListingRequest?.({ atPage })
      .then((retrievedListing) => {
        const { meta } = retrievedListing;
        setTotal(meta.total);

        return retrievedListing;
      })
      .finally(() => {
        setLoadingMoreEvents(false);
      });
  };

  const reload = (): void => {
    setPage(1);
    listEntities({ atPage: 1 })
      ?.then(({ result }) => {
        setEntities(result);
      })
      .catch(() => undefined);
  };

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(details)) {
      setEntities(undefined);
    }

    if (page !== 1 || isNil(details) || preventReloadWhen) {
      return;
    }

    reload();
  }, [details]);

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(entities) || page === 1) {
      return;
    }

    listEntities()
      ?.then(({ result }) => {
        setEntities(concat(entities, result));
      })
      .catch(() => undefined);
  }, [page]);

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(details) || isNil(entities)) {
      return;
    }

    setEntities(undefined);

    reload();
  }, reloadDependencies);

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (selectedResourceId !== details?.id) {
      setEntities(undefined);
      setPage(1);
    }
  }, [selectedResourceId]);

  const maxPage = Math.ceil(total / limit);

  const loadMoreEvents = (): void => {
    if (equals(page, maxPage)) {
      return;
    }
    setLoadingMoreEvents(true);
    setPage(page + 1);
  };

  const scroll = (event): void => {
    const { scrollTop } = event.target;
    if (preventScrollingRef.current && gt(scrollTop, 0)) {
      return;
    }
    setIsScrolling(not(equals(scrollTop, 0)));
    preventScrollingRef.current = false;

    if (gt(scrollTop, 0)) {
      return;
    }

    reload();
  };

  const scrollToTop = (): void => {
    scrollableContainerRef.current?.scrollTo({
      behavior: gt(length(entities as Array<TEntity>), 200) ? 'auto' : 'smooth',
      top: 0,
    });
    preventScrollingRef.current = true;
    setIsScrolling(false);
  };

  const infiniteScrollTriggerRef = useIntersectionObserver({
    action: loadMoreEvents,
    loading,
    maxPage,
    page,
  });

  return (
    <div
      className={classes.scrollableContainer}
<<<<<<< HEAD
      ref={scrollableContainerRef as RefObject<HTMLDivElement>}
=======
      ref={scrollableContainerRef as React.RefObject<HTMLDivElement>}
>>>>>>> centreon/dev-21.10.x
      onScroll={scroll}
    >
      <div className={classes.container}>
        <div className={classes.filter}>{filter}</div>
        <div className={classes.progress}>
          {loading && not(isNil(entities)) && (
            <LinearProgress color="primary" />
          )}
        </div>
        <div className={classes.entitiesContainer}>
          <div className={classes.entities}>
            {cond([
              [always(isNil(entities)), always(loadingSkeleton)],
              [isEmpty, always(<NoResultsMessage />)],
              [
                T,
                always(<>{children({ entities, infiniteScrollTriggerRef })}</>),
              ],
            ])(entities)}
          </div>
          <div className={classes.fab}>
            <Fade in={isScrolling}>
              <Tooltip title={t(labelScrollToTop) as string}>
                <Fab
                  aria-label={t(labelScrollToTop)}
                  color="primary"
                  size="small"
                  onClick={scrollToTop}
                >
                  <KeyboardArrowUpIcon />
                </Fab>
              </Tooltip>
            </Fade>
          </div>
        </div>
        {loadingMoreEvents && <CircularProgress />}
      </div>
    </div>
  );
};

const MemoizedInfiniteScrollContent = memoizeComponent({
  Component: InfiniteScrollContent,
  memoProps: [
<<<<<<< HEAD
    'limit',
=======
    'selectedResourceId',
    'limit',
    'details',
>>>>>>> centreon/dev-21.10.x
    'reloadDependencies',
    'loading',
    'preventReloadWhen',
    'filter',
<<<<<<< HEAD
    'details',
=======
>>>>>>> centreon/dev-21.10.x
  ],
}) as typeof InfiniteScrollContent;

const InfiniteScroll = <TEntity extends { id: number }>(
  props: Props<TEntity>,
): JSX.Element => {
<<<<<<< HEAD
  return <MemoizedInfiniteScrollContent {...props} />;
=======
  const { selectedResourceId } = useResourceContext();

  return (
    <MemoizedInfiniteScrollContent
      selectedResourceId={selectedResourceId}
      {...props}
    />
  );
>>>>>>> centreon/dev-21.10.x
};

export default InfiniteScroll;
