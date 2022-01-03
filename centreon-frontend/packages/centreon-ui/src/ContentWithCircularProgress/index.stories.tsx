import React from 'react';

import { Typography } from '@mui/material';

import ContentWithCircularLoading from '.';

export default { title: 'ContentWithCircularLoading' };

const Content = (): JSX.Element => <Typography>Loaded</Typography>;

export const loading = (): JSX.Element => (
  <ContentWithCircularLoading loading>
    <Content />
  </ContentWithCircularLoading>
);

export const loaded = (): JSX.Element => (
  <ContentWithCircularLoading loading={false}>
    <Content />
  </ContentWithCircularLoading>
);

export const loadingNotCentered = (): JSX.Element => (
  <ContentWithCircularLoading loading alignCenter={false}>
    <Content />
  </ContentWithCircularLoading>
);

export const loadingWithBiggerSize = (): JSX.Element => (
  <ContentWithCircularLoading loading loadingIndicatorSize={50}>
    <Content />
  </ContentWithCircularLoading>
);
