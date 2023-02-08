export const getDOMRangeRect = (
  nativeSelection: Selection,
  rootElement: HTMLElement
): DOMRect => {
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
