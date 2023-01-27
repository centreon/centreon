import { atom } from 'jotai';

import { Image } from './models';

export const imagesAtom = atom<Record<string, Image>>({});
