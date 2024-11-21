import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { equals } from 'ramda';

import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { ResourceListing, Visualization } from '../models';
import { baseKey } from '../storage';

import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost
} from './columns';

export const listingAtom = atom<ResourceListing | undefined>(undefined);
export const limitAtom = atomWithStorage(`${baseKey}limit`, 30);
export const pageAtom = atom<number | undefined>(undefined);
export const enabledAutorefreshAtom = atom<boolean>(true);
const columnIdsAtom = atom(defaultSelectedColumnIds);
export const selectedColumnIdsAtom = atom(
  (get) => {
    const selectedVisualization = get(selectedVisualizationAtom);
    get(columnIdsAtom);

    const columnIds = localStorage.getItem(
      `${baseKey}${selectedVisualization}-column-ids`
    );

    const defaultColumnIds = equals(selectedVisualization, Visualization.Host)
      ? defaultSelectedColumnIdsforViewByHost
      : defaultSelectedColumnIds;

    return columnIds ? JSON.parse(columnIds) : defaultColumnIds;
  },
  (get, set, newColumnIds: Array<string>) => {
    const selectedVisualization = get(selectedVisualizationAtom);

    localStorage.setItem(
      `${baseKey}${selectedVisualization}-column-ids`,
      JSON.stringify(newColumnIds)
    );
    set(columnIdsAtom, newColumnIds);
  }
);
export const sendingAtom = atom<boolean>(false);
