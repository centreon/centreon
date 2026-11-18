import { atom } from 'jotai';

import type { Downtime } from '.';
import { defaultDowntime } from './defaults';

const downtimeAtom = atom<Downtime>(defaultDowntime);

export default downtimeAtom;
