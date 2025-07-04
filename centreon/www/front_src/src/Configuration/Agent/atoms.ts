import { atom } from 'jotai';
import { AgentType } from './models';

export const agentTypeFormAtom = atom<AgentType | null>(null);
