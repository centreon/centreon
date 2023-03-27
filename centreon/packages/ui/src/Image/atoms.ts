import { atom } from 'jotai';

import { Image } from './models';

export const imagesAtom = atom<Record<string, Image>>({});

export const getAsyncImagesAtom = atom(async (get) => get(imagesAtom));
