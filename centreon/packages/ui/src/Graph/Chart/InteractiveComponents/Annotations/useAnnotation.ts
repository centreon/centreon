import { useAtomValue, useSetAtom } from 'jotai';
import { useEffect } from 'react';

import { margin } from '../../common';
import {
  eventMouseLeaveAtom,
  mousePositionAtom
} from '../interactionWithGraphAtoms';
import type { Props } from '.';
import {
  annotationHoveredAtom,
  changeAnnotationHoveredDerivedAtom
} from './annotationsAtoms';

const useAnnotation = ({
  graphWidth,
  data,
  xScale
}: Omit<Props, 'graphHeight'>): number => {
  const [annotationHoveredId] = crypto.getRandomValues(new Uint16Array(1));

  const mousePosition = useAtomValue(mousePositionAtom);
  const _mouseLeaveEvent = useAtomValue(eventMouseLeaveAtom);

  const setAnnotationHovered = useSetAtom(annotationHoveredAtom);
  const changeAnnotationHovered = useSetAtom(
    changeAnnotationHoveredDerivedAtom
  );

  useEffect(() => {
    if (!mousePosition) {
      return;
    }

    const mousePositionX = mousePosition[0] - margin.left;

    changeAnnotationHovered({
      annotationHoveredId,
      graphWidth,
      mouseX: mousePositionX,
      timeline: data,
      xScale
    });
  }, [
    mousePosition,
    annotationHoveredId,
    changeAnnotationHovered,
    data,
    graphWidth,
    xScale
  ]);

  useEffect(() => {
    setAnnotationHovered(undefined);
  }, [setAnnotationHovered]);

  return annotationHoveredId;
};

export default useAnnotation;
