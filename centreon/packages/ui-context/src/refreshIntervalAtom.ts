import { atom } from 'jotai';

import { defaultRefreshInterval } from './defaults';

const refreshIntervalAtom = atom(defaultRefreshInterval);

export default refreshIntervalAtom;
