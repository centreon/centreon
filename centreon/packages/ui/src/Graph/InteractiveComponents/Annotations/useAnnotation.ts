import { useEffect } from 'react';

import { Event } from '@visx/visx';
import { useAtomValue, useSetAtom } from 'jotai';

import { margin } from '../../common';
import {
  eventMouseLeaveAtom,
  eventMouseMovingAtom
} from '../interactionWithGraphAtoms';

import {
  annotationHoveredAtom,
  changeAnnotationHoveredDerivedAtom
} from './annotationsAtoms';

import { Props } from '.';

const useAnnotation = ({
  graphWidth,
  data,
  xScale,
  graphSvgRef
}: Omit<Props, 'graphHeight'>): number => {
  const [annotationHoveredId] = crypto.getRandomValues(new Uint16Array(1));

  const mouseMovingEvent = useAtomValue(eventMouseMovingAtom);
  const mouseLeaveEvent = useAtomValue(eventMouseLeaveAtom);

  const setAnnotationHovered = useSetAtom(annotationHoveredAtom);
  const changeAnnotationHovered = useSetAtom(
    changeAnnotationHoveredDerivedAtom
  );

  useEffect(() => {
    if (!mouseMovingEvent) {
      return;
    }
    const { x } = Event.localPoint(
      graphSvgRef.current as SVGSVGElement,
      mouseMovingEvent
    ) || { x: 0 };

    const mousePositionX = x - margin.left;

    changeAnnotationHovered({
      annotationHoveredId,
      graphWidth,
      mouseX: mousePositionX,
      timeline: data,
      xScale
    });
  }, [mouseMovingEvent]);

  useEffect(() => {
    setAnnotationHovered(undefined);
  }, [mouseLeaveEvent]);

  return annotationHoveredId;
};

export default useAnnotation;
