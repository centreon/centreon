import { useAtomValue } from 'jotai';
import { isNil, lte } from 'ramda';

import useAiAvailability from '../../AiAssistance/useAiAvailability';
import {
  panelWidthStorageAtom,
  selectedResourcesDetailsAtom
} from '../../Details/detailsAtoms';
import { type ToolbarWidths, Type, toolbarWidths } from '../model';

interface UseMediaQueryListing {
  applyBreakPoint: boolean;
  breakPointType?: Type;
  widths: ToolbarWidths;
}

const useMediaQueryListing = (): UseMediaQueryListing => {
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const selectedResourceDetails = useAtomValue(selectedResourcesDetailsAtom);

  const { isConfigured } = useAiAvailability();
  const widths = isConfigured ? toolbarWidths.withAi : toolbarWidths.default;

  const isPanelOpen = !isNil(selectedResourceDetails?.resourceId);

  const newWidth = window.innerWidth - panelWidth;

  const width = isPanelOpen ? newWidth : window.innerWidth;

  const applyBreakPoint = lte(width, widths.medium);

  const breakPointType =
    applyBreakPoint && lte(width, widths.small) ? Type.small : undefined;

  return { applyBreakPoint, breakPointType, widths };
};

export default useMediaQueryListing;
