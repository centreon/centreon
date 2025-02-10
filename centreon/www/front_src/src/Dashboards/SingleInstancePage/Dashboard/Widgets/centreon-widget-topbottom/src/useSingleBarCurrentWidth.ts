import { RefObject, useEffect, useState } from 'react';

interface Props {
  containerRef: RefObject<HTMLDivElement | null>;
  labelRef: RefObject<HTMLParagraphElement | null>;
}

const useSingleBarCurrentWidth = ({
  containerRef,
  labelRef
}: Props): string | number => {
  const [currentContainerWidth, setCurrentContainerWidth] = useState();
  const [currentLabelWidth, setCurrentLabelWidth] = useState();

  const getElementCurrentWidth = ({ element, setWidth }) => {
    const observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        setWidth(entry.contentRect.width);
      }
    });

    observer.observe(element);

    return () => observer.disconnect();
  };

  useEffect(() => {
    if (!containerRef?.current) {
      return;
    }
    return getElementCurrentWidth({
      element: containerRef.current,
      setWidth: setCurrentContainerWidth
    });
  }, [containerRef?.current]);

  useEffect(() => {
    if (!labelRef?.current) {
      return;
    }

    return getElementCurrentWidth({
      element: labelRef.current,
      setWidth: setCurrentLabelWidth
    });
  }, [labelRef?.current]);

  const getSingleBarWidth = () => {
    if (!currentContainerWidth || !currentLabelWidth) {
      return '100%';
    }
    return currentContainerWidth - currentLabelWidth - 24;
  };

  return getSingleBarWidth();
};

export default useSingleBarCurrentWidth;
