import { isNil } from 'ramda';

export const getDOMRangeRect = (
  nativeSelection: Selection | null,
  rootElement: HTMLElement | null
): DOMRect | null => {
  if (isNil(nativeSelection) || isNil(rootElement)) {
    return null;
  }

  const domRange = nativeSelection.getRangeAt(0);
  if (nativeSelection.anchorNode === rootElement) {
    let inner = rootElement;
    while (inner.firstElementChild != null) {
      inner = inner.firstElementChild as HTMLElement;
    }

    return inner.getBoundingClientRect();
  }

  return domRange.getBoundingClientRect();
};
