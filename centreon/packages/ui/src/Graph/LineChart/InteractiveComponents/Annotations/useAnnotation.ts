import { useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { margin } from '../../common';
import {
  eventMouseLeaveAtom,
  mousePositionAtom
} from '../interactionWithGraphAtoms';

import {
  annotationHoveredAtom,
  changeAnnotationHoveredDerivedAtom
} from './annotationsAtoms';

import { Props } from '.';

const useAnnotation = ({
  graphWidth,
  data,
  xScale
}: Omit<Props, 'graphHeight'>): number => {
  const [annotationHoveredId] = crypto.getRandomValues(new Uint16Array(1));

  const mousePosition = useAtomValue(mousePositionAtom);
  const mouseLeaveEvent = useAtomValue(eventMouseLeaveAtom);

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
  }, [mousePosition]);

  useEffect(() => {
    setAnnotationHovered(undefined);
  }, [mouseLeaveEvent]);

  return annotationHoveredId;
};

export default useAnnotation;
