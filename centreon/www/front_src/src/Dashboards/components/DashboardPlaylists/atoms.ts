import { atom } from 'jotai';

import { PlaylistConfig } from './models';

export const playlistConfigInitialValuesAtom = atom<PlaylistConfig | null>(
  null
);

export const askBeforeClosePlaylistConfigAtom = atom(false);
