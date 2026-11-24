import { atom } from 'jotai';

import { Resource } from '../models';
import { AiResult } from './models';

// The Ask AI explanation dialog lives in the store rather than in the state of
// AskAiFilter, so that it survives a re-render or a remount of the filter bar
// while the request is in flight.
export const askAiDialogOpenAtom = atom(false);
// null while the answer is loading
export const askAiResultAtom = atom<AiResult | null>(null);
export const askAiExplainedResourceAtom = atom<Resource | null>(null);
// the latest Ask AI request (see useLatestRequest)
export const askAiRequestIdAtom = atom(0);
