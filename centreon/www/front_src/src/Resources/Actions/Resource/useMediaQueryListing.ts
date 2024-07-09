import { useAtomValue } from 'jotai';
import { isNil, lte } from 'ramda';

import {
  panelWidthStorageAtom,
  selectedResourcesDetailsAtom
} from '../../Details/detailsAtoms';
import { Type, mediumWidth, smallWidth } from '../model';

interface UseMediaQueryListing {
  applyBreakPoint: boolean;
  breakPointType?: Type;
}

const useMediaQueryListing = (): UseMediaQueryListing => {
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const selectedResourceDetails = useAtomValue(selectedResourcesDetailsAtom);

  const isPanelOpen = !isNil(selectedResourceDetails?.resourceId);

  const newWidth = window.innerWidth - panelWidth;

  const width = isPanelOpen ? newWidth : window.innerWidth;

  const applyBreakPoint = lte(width, mediumWidth);

  const breakPointType =
    applyBreakPoint && lte(width, smallWidth) ? Type.small : undefined;

  return { applyBreakPoint, breakPointType };
};

export default useMediaQueryListing;
