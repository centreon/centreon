import { atom, Provider } from 'jotai';

import { defaultAcknowledgement } from './defaults';

import { Acknowledgement } from '.';

const acknowledgementAtom = atom<Acknowledgement>(defaultAcknowledgement);

export default acknowledgementAtom;
