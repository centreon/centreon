import { atom } from 'jotai';

import { GraphOptions } from '../../../Details/models';
import { labelDisplayEvents } from '../../../translatedLabels';
import { GraphOptionId } from '../models';

export const defaultGraphOptions = {
  [GraphOptionId.displayEvents]: {
    id: GraphOptionId.displayEvents,
    label: labelDisplayEvents,
    value: false
  }
};

export const graphOptionsAtom = atom<GraphOptions>(defaultGraphOptions);

export const changeGraphOptionsDerivedAtom = atom(
  null,
  (get, set, { graphOptionId, changeTabGraphOptions }) => {
    const graphOptions = get(graphOptionsAtom);

    const graphOptionsRecord = graphOptions as unknown as Record<
      string,
      { id: string; label: string; value: boolean }
    >;
    const newGraphOptions = {
      ...graphOptions,
      [graphOptionId]: {
        ...graphOptionsRecord[graphOptionId],
        value: !graphOptionsRecord[graphOptionId].value
      }
    };
    set(graphOptionsAtom, newGraphOptions);
    changeTabGraphOptions(newGraphOptions);
  }
);
