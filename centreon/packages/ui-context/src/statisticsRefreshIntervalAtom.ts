import { atom } from 'jotai';
import { defaultRefreshInterval } from './defaults';

const statisticsRefreshIntervalAtom = atom(defaultRefreshInterval);

export default statisticsRefreshIntervalAtom;
