import { atom } from 'jotai';

import { defaultDowntime } from './defaults';

import { Downtime } from '.';

const downtimeAtom = atom<Downtime>(defaultDowntime);

export default downtimeAtom;
