import { useEffect } from 'react';

import { isEmpty } from 'ramda';
import { useAtomValue } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import { Box, Theme } from '@mui/material';

import { useFetchQuery, PageSkeleton } from '@centreon/ui';

import { isPanelOpenAtom, reloadAtom } from './atom';
import EmptyNotificationsPage from './EmptyNotificationsPage';
import ListingPage from './ListingPage';
import { NotificationsListingType } from './models';
import { buildNotificationsEndpoint } from './Listing/api/endpoints';
import { listingDecoder } from './Listing/api/decoders';

const useStyle = makeStyles()((theme: Theme) => ({
  box: {
    marginTop: theme.spacing(1)
  }
}));

export const NotificationsPage = (): JSX.Element => {
  const { classes } = useStyle();
  const reload = useAtomValue(reloadAtom);
  const isOpen = useAtomValue(isPanelOpenAtom);

  const { data, isLoading, refetch } = useFetchQuery<NotificationsListingType>({
    decoder: listingDecoder,
    getEndpoint: () => buildNotificationsEndpoint({}),
    getQueryKey: () => ['notificationsListing'],
    queryOptions: {
      suspense: false
    }
  });

  useEffect(() => {
    if (reload) {
      refetch();
    }
  }, [reload]);

  if (isLoading) {
    return <PageSkeleton />;
  }

  if (isOpen || !isEmpty(data?.result)) {
    return (
      <Box className={classes.box}>
        <ListingPage />
      </Box>
    );
  }

  return (
    <Box className={classes.box}>
      <EmptyNotificationsPage />
    </Box>
  );
};

export default NotificationsPage;
