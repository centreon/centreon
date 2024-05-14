import { lazy } from 'react';

export const routes = {
  '/dashboards/playlists/[hash]': lazy(
    () => import('./dashboards/playlists/[hash]/page')
  )
};
